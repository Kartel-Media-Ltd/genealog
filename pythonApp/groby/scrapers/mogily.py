"""
MogilyScraper — scraper dla mogily.pl (internetowa baza osób pochowanych)

Zasięg: kilkanaście większych miast Polski.
Dodatkowa funkcja: lokalizacja GPS grobu (aplikacja mobilna).

⚠️  WYMAGANA INSPEKCJA DOM przed uruchomieniem:
    DevTools → Network na mogily.pl podczas wyszukiwania.
"""

import logging
from typing import Optional

import httpx
from bs4 import BeautifulSoup

from .base import BaseScraper

logger = logging.getLogger(__name__)


class MogilyScraper(BaseScraper):

    BASE_URL = "http://mogily.pl/start"
    RATE_LIMIT = 2.0
    MAX_RESULTS = 50

    async def _do_search(
        self,
        last_name: str,
        first_name: str = "",
        birth_year: Optional[int] = None,
        region: Optional[str] = None,
    ) -> list[dict]:
        # Mogily.pl używa formularza POST lub GET — wymaga weryfikacji w DevTools
        params = {
            "nazwisko": last_name,
            "imie": first_name,
        }

        logger.info("[Mogily] Wyszukuję: %s %s", first_name, last_name)

        async with httpx.AsyncClient(
            headers=self.HEADERS,
            timeout=self.TIMEOUT,
            follow_redirects=True,
        ) as client:
            response = await client.get(self.BASE_URL, params=params)
            response.raise_for_status()

        results = self._parse_results(response.text)
        logger.info("[Mogily] Znaleziono %d wyników.", len(results))
        return results

    def _parse_results(self, html: str) -> list[dict]:
        """
        ⚠️  SELEKTORY WYMAGAJĄ WERYFIKACJI w DevTools.
        """
        soup = BeautifulSoup(html, "html.parser")
        results = []

        rows = soup.select("table tr, .result, .osoba, .pochowany")
        for row in rows[1:]:
            cols = row.select("td")
            if len(cols) < 3:
                continue
            try:
                full_name = cols[0].get_text(strip=True)
                birth_text = cols[1].get_text(strip=True) if len(cols) > 1 else ""
                death_text = cols[2].get_text(strip=True) if len(cols) > 2 else ""
                cemetery = cols[3].get_text(strip=True) if len(cols) > 3 else ""
                location = cols[4].get_text(strip=True) if len(cols) > 4 else ""

                link = row.find("a")
                href = link["href"] if link and link.get("href") else ""
                url = href if href.startswith("http") else f"http://mogily.pl{href}" if href else None

                parts = full_name.split(maxsplit=1)
                results.append({
                    "source": "Mogily.pl",
                    "first_name": parts[1] if len(parts) > 1 else "",
                    "last_name": parts[0] if parts else full_name,
                    "birth_year": self._extract_year(birth_text),
                    "death_year": self._extract_year(death_text),
                    "cemetery": cemetery,
                    "grave_location": location,
                    "url": url,
                })
            except Exception as exc:
                logger.debug("[Mogily] Błąd parsowania wiersza: %s", exc)

        if not results:
            text = soup.get_text(strip=True).lower()
            if not any(p in text for p in ["brak", "nie znaleziono", "no results"]):
                logger.warning(
                    "[Mogily] 0 wyników — sprawdź selektory CSS. Tytuł: %s",
                    soup.title.string if soup.title else "—"
                )

        return results
