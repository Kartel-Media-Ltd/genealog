"""
eCmentarzeScraper — scraper dla ecmentarze.pl

eCmentarze to ogólnopolska baza pochowanych z 2,36 mln rekordów.
System B2B dla administratorów cmentarzy. Brak publicznego API.

⚠️  PRZED URUCHOMIENIEM: sprawdź ecmentarze.pl/robots.txt
    Jeśli robots.txt blokuje → ustaw ECMENTARZE_ENABLED=false w .env

⚠️  WYMAGANA INSPEKCJA DOM: selektory CSS wymagają weryfikacji.
"""

import logging
from typing import Optional

import httpx
from bs4 import BeautifulSoup

from .base import BaseScraper

logger = logging.getLogger(__name__)


class eCmentarzeScraper(BaseScraper):
    """
    Scraper dla ecmentarze.pl.

    Wyszukiwarka dostępna pod /wyszukaj-pochowanego.

    ⚠️  WYMAGANA INSPEKCJA DOM przed uruchomieniem na produkcji:
        1. Otwórz ecmentarze.pl/wyszukaj-pochowanego
        2. DevTools → Network → wyszukaj osobę
        3. Sprawdź URL, metodę (GET/POST) i parametry
        4. Zaktualizuj SEARCH_URL, METHOD i _parse_results()
    """

    BASE_URL = "https://www.ecmentarze.pl/wyszukaj-pochowanego"
    RATE_LIMIT = 2.0
    MAX_RESULTS = 50

    # Metoda HTTP — wymaga weryfikacji (GET lub POST)
    METHOD = "GET"

    async def _do_search(
        self,
        last_name: str,
        first_name: str = "",
        birth_year: Optional[int] = None,
        region: Optional[str] = None,
    ) -> list[dict]:
        params = {
            "nazwisko": last_name,
            "imie": first_name,
        }
        if birth_year:
            params["rok_urodzenia"] = str(birth_year)

        logger.info(
            "[eCmentarze] Wyszukuję: %s %s",
            first_name, last_name
        )

        async with httpx.AsyncClient(
            headers=self.HEADERS,
            timeout=self.TIMEOUT,
            follow_redirects=True,
        ) as client:
            if self.METHOD == "POST":
                response = await client.post(self.BASE_URL, data=params)
            else:
                response = await client.get(self.BASE_URL, params=params)
            response.raise_for_status()

        results = self._parse_results(response.text)
        logger.info("[eCmentarze] Znaleziono %d wyników.", len(results))
        return results

    def _parse_results(self, html: str) -> list[dict]:
        """
        Parsuje HTML wyników eCmentarze.

        ⚠️  SELEKTORY CSS WYMAGAJĄ WERYFIKACJI w DevTools.
        Poniższe selektory są szacunkowe.
        """
        soup = BeautifulSoup(html, "html.parser")
        results = []

        # Próba: wiersze tabeli wyników
        rows = soup.select("table tr, .wynik, .person-row, .result-row")
        for row in rows[1:]:  # Pomiń nagłówek
            cols = row.select("td")
            if len(cols) < 3:
                continue

            try:
                full_name = cols[0].get_text(strip=True)
                birth_text = cols[1].get_text(strip=True) if len(cols) > 1 else ""
                death_text = cols[2].get_text(strip=True) if len(cols) > 2 else ""
                cemetery = cols[3].get_text(strip=True) if len(cols) > 3 else ""
                location = cols[4].get_text(strip=True) if len(cols) > 4 else ""

                link = cols[0].find("a") or row.find("a")
                href = link["href"] if link and link.get("href") else ""
                url = href if href.startswith("http") else f"https://www.ecmentarze.pl{href}" if href else None

                parts = full_name.split(maxsplit=1)
                last = parts[0] if parts else full_name
                first = parts[1] if len(parts) > 1 else ""

                results.append({
                    "source": "eCmentarze",
                    "first_name": first,
                    "last_name": last,
                    "birth_year": self._extract_year(birth_text),
                    "death_year": self._extract_year(death_text),
                    "cemetery": cemetery,
                    "grave_location": location,
                    "url": url,
                })
            except Exception as exc:
                logger.debug("[eCmentarze] Błąd parsowania wiersza: %s", exc)

        if not results:
            text = soup.get_text(strip=True).lower()
            if not any(p in text for p in ["brak wyników", "nie znaleziono", "no results"]):
                logger.warning(
                    "[eCmentarze] 0 wyników ale brak komunikatu 'brak'. "
                    "Sprawdź selektory CSS w _parse_results(). "
                    "Tytuł: %s",
                    soup.title.string if soup.title else "—"
                )

        return results
