"""
GrobonetScraper — scraper dla grobonet.com

Grobonet to największy portal cmentarny w Polsce (~600 cmentarzy komunalnych
i parafialnych). Brak publicznego API — dane dostępne przez formularz HTML.

UWAGA: selektory CSS i parametry zapytań mogą wymagać aktualizacji po zmianie
struktury HTML strony. Przy 0 wynikach — sprawdź _parse_results() w DevTools.

Precedens scrapingu: github.com/Cisowscy/grobonet-for-gedcom

Etykieta:
  - RATE_LIMIT = 2.0s (podwójny margines bezpieczeństwa)
  - User-Agent identyfikujący z adresem kontaktowym
  - robots.txt sprawdzany przed pierwszym zapytaniem
"""

import logging
from typing import Optional

import httpx
from bs4 import BeautifulSoup

from .base import BaseScraper

logger = logging.getLogger(__name__)


class GrobonetScraper(BaseScraper):
    """
    Scraper dla grobonet.com.

    Metoda dostępu: GET /index.php z parametrami wyszukiwania.

    ⚠️  WYMAGANA INSPEKCJA DOM przed uruchomieniem na produkcji:
        Otwórz grobonet.com → DevTools → Network → wyszukaj osobę
        → sprawdź URL zapytania i strukturę tabeli wyników.
        Zaktualizuj SEARCH_PARAMS_MAP i selektory w _parse_results().
    """

    BASE_URL = "https://grobonet.com/index.php"
    RATE_LIMIT = 2.0
    MAX_RESULTS = 50

    # Mapowanie parametrów zapytania — wymaga weryfikacji w DevTools
    # Typowe parametry Grobonet (na podstawie github.com/Cisowscy/grobonet-for-gedcom)
    SEARCH_PARAMS_MAP = {
        "op": "se",           # tryb wyszukiwania
        "imie": "",           # imię
        "nazwisko": "",       # nazwisko
        "cmentarz": "",       # ID cmentarza (pusty = wszystkie)
        "rok_od": "",         # rok urodzenia od
        "rok_do": "",         # rok urodzenia do
    }

    async def _do_search(
        self,
        last_name: str,
        first_name: str = "",
        birth_year: Optional[int] = None,
        region: Optional[str] = None,
    ) -> list[dict]:
        params = dict(self.SEARCH_PARAMS_MAP)
        params["nazwisko"] = last_name
        params["imie"] = first_name

        if birth_year:
            # Okno ±5 lat wokół roku urodzenia
            params["rok_od"] = str(birth_year - 5)
            params["rok_do"] = str(birth_year + 5)

        logger.info(
            "[Grobonet] Wyszukuję: %s %s (rok: %s)",
            first_name, last_name, birth_year or "—"
        )

        async with httpx.AsyncClient(
            headers=self.HEADERS,
            timeout=self.TIMEOUT,
            follow_redirects=True,
        ) as client:
            response = await client.get(self.BASE_URL, params=params)
            response.raise_for_status()

        results = self._parse_results(response.text, last_name, first_name)
        logger.info("[Grobonet] Znaleziono %d wyników.", len(results))
        return results

    def _parse_results(self, html: str, last_name: str, first_name: str) -> list[dict]:
        """
        Parsuje HTML odpowiedzi Grobonet.

        ⚠️  WYMAGANA WERYFIKACJA SELEKTORÓW:
        Poniższe selektory są szacunkowe. Przed uruchomieniem:
        1. Otwórz grobonet.com w przeglądarce
        2. Wyszukaj dowolną osobę
        3. DevTools → Elements → znajdź tabelę wyników
        4. Zaktualizuj selektory poniżej

        Typowa struktura Grobonet (może się różnić):
          <div class="result-item" data-id="12345">
            <span class="name">Jan Kowalski</span>
            <span class="dates">1920 - 2001</span>
            <span class="cemetery">Cmentarz Komunalny Warszawa-Północ</span>
            <span class="location">Kwatera A, Rząd 3, Miejsce 15</span>
          </div>
        """
        soup = BeautifulSoup(html, "html.parser")
        results = []

        # ----------------------------------------------------------------
        # Próba 1: Tabela wyników (najczęstszy format)
        # ----------------------------------------------------------------
        rows = soup.select("table.wyniki tr, table.results tr, .result-table tr")
        if rows:
            for row in rows[1:]:  # Pomiń nagłówek
                cols = row.select("td")
                if len(cols) < 3:
                    continue
                result = self._parse_table_row(cols)
                if result:
                    results.append(result)

        # ----------------------------------------------------------------
        # Próba 2: Divy/karty wyników (alternatywny layout)
        # ----------------------------------------------------------------
        if not results:
            cards = soup.select(".result-item, .osoba, .pochowany, [data-id]")
            for card in cards:
                result = self._parse_card(card)
                if result:
                    results.append(result)

        # ----------------------------------------------------------------
        # Fallback: loguj HTML dla debugowania gdy 0 wyników
        # ----------------------------------------------------------------
        if not results:
            # Sprawdź czy strona zawiera komunikat "brak wyników"
            text = soup.get_text(strip=True).lower()
            if any(phrase in text for phrase in ["brak wyników", "nie znaleziono", "no results"]):
                logger.debug("[Grobonet] Brak wyników dla zapytania.")
            else:
                logger.warning(
                    "[Grobonet] Brak wyników ale strona nie zawiera komunikatu 'brak'. "
                    "Sprawdź selektory CSS w _parse_results(). "
                    "Tytuł strony: %s",
                    soup.title.string if soup.title else "—"
                )

        return results

    def _parse_table_row(self, cols: list) -> Optional[dict]:
        """Parsuje wiersz tabeli wyników."""
        try:
            # Format kolumn (weryfikuj w DevTools):
            # 0: nazwisko imię | 1: data ur. | 2: data śm. | 3: cmentarz | 4: lokalizacja
            full_name = cols[0].get_text(strip=True)
            birth_text = cols[1].get_text(strip=True) if len(cols) > 1 else ""
            death_text = cols[2].get_text(strip=True) if len(cols) > 2 else ""
            cemetery = cols[3].get_text(strip=True) if len(cols) > 3 else ""
            location = cols[4].get_text(strip=True) if len(cols) > 4 else ""

            # Link do szczegółów
            link = cols[0].find("a")
            url = f"https://grobonet.com{link['href']}" if link and link.get("href") else None

            # Podział imię/nazwisko
            parts = full_name.split(maxsplit=1)
            last = parts[0] if parts else full_name
            first = parts[1] if len(parts) > 1 else ""

            return {
                "source": "Grobonet",
                "first_name": first,
                "last_name": last,
                "birth_year": self._extract_year(birth_text),
                "death_year": self._extract_year(death_text),
                "cemetery": cemetery,
                "grave_location": location,
                "url": url,
            }
        except Exception as exc:
            logger.debug("[Grobonet] Błąd parsowania wiersza: %s", exc)
            return None

    def _parse_card(self, card) -> Optional[dict]:
        """Parsuje kartę/div wyników (alternatywny layout)."""
        try:
            name_el = card.select_one(".name, .nazwisko, h3, h4")
            dates_el = card.select_one(".dates, .daty, .lata")
            cemetery_el = card.select_one(".cemetery, .cmentarz")
            location_el = card.select_one(".location, .lokalizacja, .miejsce")

            full_name = name_el.get_text(strip=True) if name_el else ""
            if not full_name:
                return None

            dates_text = dates_el.get_text(strip=True) if dates_el else ""
            import re
            years = re.findall(r"\b(1[0-9]{3}|20[0-2][0-9])\b", dates_text)

            data_id = card.get("data-id", "")
            url = f"https://grobonet.com/?id={data_id}" if data_id else None

            parts = full_name.split(maxsplit=1)
            last = parts[0] if parts else full_name
            first = parts[1] if len(parts) > 1 else ""

            return {
                "source": "Grobonet",
                "first_name": first,
                "last_name": last,
                "birth_year": int(years[0]) if years else None,
                "death_year": int(years[1]) if len(years) > 1 else None,
                "cemetery": cemetery_el.get_text(strip=True) if cemetery_el else "",
                "grave_location": location_el.get_text(strip=True) if location_el else "",
                "url": url,
            }
        except Exception as exc:
            logger.debug("[Grobonet] Błąd parsowania karty: %s", exc)
            return None
