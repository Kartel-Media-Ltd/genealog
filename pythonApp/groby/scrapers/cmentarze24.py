"""
Cmentarze24Scraper — scraper dla cmentarze24.pl

Wyróżnik: nekrologi i klepsydry — unikalne dla genealogii.
Nekrolog potwierdza datę śmierci i często wymienia skład rodziny
(współmałżonek, dzieci, wnuki) — bardzo cenne dla budowania drzewa.

Zasięg: Polska + Polonia zagraniczna.

⚠️  WYMAGANA INSPEKCJA DOM przed uruchomieniem.
"""

import logging
from typing import Optional

import httpx
from bs4 import BeautifulSoup

from .base import BaseScraper

logger = logging.getLogger(__name__)


class Cmentarze24Scraper(BaseScraper):
    """
    Scraper dla cmentarze24.pl — nekrologi i wpisy cmentarne.

    Zwraca rozszerzony rekord z polem `obituary_text` (treść nekrologu),
    który może zawierać informacje o rodzinie.
    """

    BASE_URL = "https://www.cmentarze24.pl"
    SEARCH_URL = "https://www.cmentarze24.pl/szukaj"
    RATE_LIMIT = 2.0
    MAX_RESULTS = 50

    async def _do_search(
        self,
        last_name: str,
        first_name: str = "",
        birth_year: Optional[int] = None,
        region: Optional[str] = None,
    ) -> list[dict]:
        params = {
            "q": f"{first_name} {last_name}".strip(),
            "nazwisko": last_name,
            "imie": first_name,
        }

        logger.info("[Cmentarze24] Wyszukuję: %s %s", first_name, last_name)

        async with httpx.AsyncClient(
            headers=self.HEADERS,
            timeout=self.TIMEOUT,
            follow_redirects=True,
        ) as client:
            response = await client.get(self.SEARCH_URL, params=params)
            response.raise_for_status()

        results = self._parse_results(response.text)
        logger.info("[Cmentarze24] Znaleziono %d wyników.", len(results))
        return results

    def _parse_results(self, html: str) -> list[dict]:
        """
        Parsuje HTML wyników — nekrologi i wpisy cmentarne.

        ⚠️  SELEKTORY WYMAGAJĄ WERYFIKACJI w DevTools.

        Rekord rozszerzony o:
          - obituary_text: treść nekrologu (może zawierać skład rodziny)
          - record_type: 'nekrolog' | 'grob' | 'klepsydra'
        """
        soup = BeautifulSoup(html, "html.parser")
        results = []

        # Próba 1: Karty wyników (typowy format portali z nekrologami)
        cards = soup.select(".nekrolog, .wynik, .osoba-card, .result-card, article")
        for card in cards:
            try:
                result = self._parse_card(card)
                if result:
                    results.append(result)
            except Exception as exc:
                logger.debug("[Cmentarze24] Błąd parsowania karty: %s", exc)

        # Próba 2: Tabela (fallback)
        if not results:
            rows = soup.select("table tr")
            for row in rows[1:]:
                cols = row.select("td")
                if len(cols) < 2:
                    continue
                try:
                    full_name = cols[0].get_text(strip=True)
                    if not full_name:
                        continue
                    birth_text = cols[1].get_text(strip=True) if len(cols) > 1 else ""
                    death_text = cols[2].get_text(strip=True) if len(cols) > 2 else ""
                    cemetery = cols[3].get_text(strip=True) if len(cols) > 3 else ""

                    link = row.find("a")
                    href = link["href"] if link and link.get("href") else ""
                    url = href if href.startswith("http") else f"{self.BASE_URL}{href}" if href else None

                    parts = full_name.split(maxsplit=1)
                    results.append({
                        "source": "Cmentarze24",
                        "first_name": parts[1] if len(parts) > 1 else "",
                        "last_name": parts[0] if parts else full_name,
                        "birth_year": self._extract_year(birth_text),
                        "death_year": self._extract_year(death_text),
                        "cemetery": cemetery,
                        "grave_location": "",
                        "url": url,
                        "obituary_text": None,
                        "record_type": "grob",
                    })
                except Exception as exc:
                    logger.debug("[Cmentarze24] Błąd parsowania wiersza: %s", exc)

        if not results:
            text = soup.get_text(strip=True).lower()
            if not any(p in text for p in ["brak", "nie znaleziono", "no results", "0 wyników"]):
                logger.warning(
                    "[Cmentarze24] 0 wyników — sprawdź selektory CSS. Tytuł: %s",
                    soup.title.string if soup.title else "—"
                )

        return results

    def _parse_card(self, card) -> Optional[dict]:
        """Parsuje kartę nekrologu/grobu."""
        name_el = card.select_one(".name, .nazwisko, h2, h3, h4, .title")
        if not name_el:
            return None

        full_name = name_el.get_text(strip=True)
        if not full_name:
            return None

        # Daty
        dates_el = card.select_one(".dates, .daty, .lata, .birth-death")
        dates_text = dates_el.get_text(strip=True) if dates_el else ""
        import re
        years = re.findall(r"\b(1[0-9]{3}|20[0-2][0-9])\b", dates_text)

        # Nekrolog / klepsydra
        obit_el = card.select_one(".opis, .tresc, .content, .obituary, p")
        obituary_text = obit_el.get_text(strip=True)[:500] if obit_el else None

        # Typ rekordu
        classes = " ".join(card.get("class", []))
        if "nekrolog" in classes.lower():
            record_type = "nekrolog"
        elif "klepsydra" in classes.lower():
            record_type = "klepsydra"
        else:
            record_type = "grob"

        # Link
        link = card.find("a")
        href = link["href"] if link and link.get("href") else ""
        url = href if href.startswith("http") else f"{self.BASE_URL}{href}" if href else None

        # Cmentarz
        cemetery_el = card.select_one(".cemetery, .cmentarz, .miejsce-pochowku")
        cemetery = cemetery_el.get_text(strip=True) if cemetery_el else ""

        parts = full_name.split(maxsplit=1)
        return {
            "source": "Cmentarze24",
            "first_name": parts[1] if len(parts) > 1 else "",
            "last_name": parts[0] if parts else full_name,
            "birth_year": int(years[0]) if years else None,
            "death_year": int(years[1]) if len(years) > 1 else None,
            "cemetery": cemetery,
            "grave_location": "",
            "url": url,
            "obituary_text": obituary_text,  # pole dodatkowe — tekst nekrologu
            "record_type": record_type,
        }
