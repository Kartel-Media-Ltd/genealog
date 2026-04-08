"""
BaseScraper — wspólna klasa bazowa dla wszystkich scraperów rejestrów cmentarnych.

Zapewnia:
- rate limiting (sleep między zapytaniami)
- weryfikację robots.txt przed pierwszym zapytaniem
- retry z exponential backoff (3 próby)
- wspólne nagłówki HTTP (User-Agent identyfikujący aplikację)
- timeout 10s
"""

import asyncio
import logging
import urllib.robotparser
from abc import ABC, abstractmethod
from typing import Optional

import httpx

logger = logging.getLogger(__name__)

# User-Agent identyfikujący aplikację — etykieta scrapingu.
# Pozwala administratorom serwisu zidentyfikować źródło i skontaktować się.
USER_AGENT = "Genealog.pl Research Tool/1.0 (kontakt@genealog.pl; https://genealog.pl)"


class ScraperError(Exception):
    """Wyjątek scrapera — niezależny od błędów HTTP."""
    pass


class RobotsBlocked(ScraperError):
    """robots.txt blokuje dostęp do zasobu."""
    pass


class BaseScraper(ABC):
    """
    Abstrakcyjna klasa bazowa dla scraperów rejestrów cmentarnych.

    Każdy scraper dziedziczy i implementuje:
      - BASE_URL: str
      - RATE_LIMIT: float (sekundy między requestami, min. 1.0)
      - _do_search(...) -> list[dict]
    """

    BASE_URL: str = ""
    RATE_LIMIT: float = 2.0      # sekundy między requestami
    MAX_RETRIES: int = 3
    TIMEOUT: float = 10.0        # sekund na request
    MAX_RESULTS: int = 50        # limit wyników z jednego zapytania

    HEADERS: dict[str, str] = {
        "User-Agent": USER_AGENT,
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
        "Accept-Language": "pl-PL,pl;q=0.9,en;q=0.8",
    }

    def __init__(self) -> None:
        self._robots_checked: bool = False
        self._robots_allowed: bool = True
        self._last_request_time: float = 0.0

    # ------------------------------------------------------------------
    # Publiczne API
    # ------------------------------------------------------------------

    async def search(
        self,
        last_name: str,
        first_name: str = "",
        birth_year: Optional[int] = None,
        region: Optional[str] = None,
    ) -> list[dict]:
        """
        Główna metoda wyszukiwania. Sprawdza robots.txt, rate limit,
        wykonuje retry i deleguje do _do_search().
        """
        if not last_name or len(last_name.strip()) < 2:
            raise ValueError("Nazwisko musi mieć co najmniej 2 znaki.")

        await self._check_robots()
        await self._rate_limit_sleep()

        last_exc: Optional[Exception] = None
        for attempt in range(1, self.MAX_RETRIES + 1):
            try:
                results = await self._do_search(
                    last_name=last_name.strip(),
                    first_name=first_name.strip(),
                    birth_year=birth_year,
                    region=region,
                )
                return results[: self.MAX_RESULTS]
            except (httpx.TimeoutException, httpx.NetworkError) as exc:
                last_exc = exc
                wait = 2 ** attempt  # exponential backoff: 2s, 4s, 8s
                logger.warning(
                    "[%s] Próba %d/%d nieudana: %s. Czekam %ds.",
                    self.__class__.__name__, attempt, self.MAX_RETRIES, exc, wait,
                )
                if attempt < self.MAX_RETRIES:
                    await asyncio.sleep(wait)
            except RobotsBlocked:
                raise
            except Exception as exc:
                last_exc = exc
                logger.error("[%s] Błąd nieoczekiwany: %s", self.__class__.__name__, exc)
                break

        raise ScraperError(
            f"Wyszukiwanie nieudane po {self.MAX_RETRIES} próbach: {last_exc}"
        )

    # ------------------------------------------------------------------
    # Do nadpisania przez podklasy
    # ------------------------------------------------------------------

    @abstractmethod
    async def _do_search(
        self,
        last_name: str,
        first_name: str,
        birth_year: Optional[int],
        region: Optional[str],
    ) -> list[dict]:
        """Właściwa logika scrapingu — implementowana przez podklasę."""
        ...

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------

    async def _check_robots(self) -> None:
        """Sprawdza robots.txt przy pierwszym wywołaniu (cache na instancję)."""
        if self._robots_checked:
            if not self._robots_allowed:
                raise RobotsBlocked(f"robots.txt blokuje scraping {self.BASE_URL}")
            return

        robots_url = self._robots_url()
        try:
            async with httpx.AsyncClient(headers=self.HEADERS, timeout=5.0) as client:
                r = await client.get(robots_url)
            rp = urllib.robotparser.RobotFileParser()
            rp.set_url(robots_url)
            rp.parse(r.text.splitlines())
            self._robots_allowed = rp.can_fetch(USER_AGENT, self.BASE_URL)
            if not self._robots_allowed:
                logger.warning(
                    "[%s] robots.txt BLOKUJE dostęp do %s",
                    self.__class__.__name__, self.BASE_URL,
                )
        except Exception as exc:
            # Jeśli robots.txt niedostępny — przyjmij że dozwolony
            logger.debug("[%s] Nie można sprawdzić robots.txt: %s", self.__class__.__name__, exc)
            self._robots_allowed = True
        finally:
            self._robots_checked = True

        if not self._robots_allowed:
            raise RobotsBlocked(f"robots.txt blokuje scraping {self.BASE_URL}")

    async def _rate_limit_sleep(self) -> None:
        """Czeka jeśli poprzedni request był zbyt niedawno."""
        import time
        now = time.monotonic()
        elapsed = now - self._last_request_time
        if elapsed < self.RATE_LIMIT:
            await asyncio.sleep(self.RATE_LIMIT - elapsed)
        self._last_request_time = time.monotonic()

    def _robots_url(self) -> str:
        from urllib.parse import urlparse
        parsed = urlparse(self.BASE_URL)
        return f"{parsed.scheme}://{parsed.netloc}/robots.txt"

    @staticmethod
    def _extract_year(text: str) -> Optional[int]:
        """Wyciąga rok z tekstu w formacie 'DD.MM.YYYY' lub samo 'YYYY'."""
        import re
        m = re.search(r"\b(1[0-9]{3}|20[0-2][0-9])\b", text)
        return int(m.group(1)) if m else None
