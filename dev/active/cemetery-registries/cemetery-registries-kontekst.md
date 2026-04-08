# Rejestry Cmentarne — Kontekst i Badania

> Zbadano: 2026-04-08. Źródła: web research + analiza codebase.

---

## Które rejestry cmentarne istnieją w Polsce?

### 1. Grobonet (grobonet.com) ⭐⭐⭐⭐

**Zasięg:** Ponad 600 cmentarzy w Polsce, system B2B dla administratorów cmentarzy komunalnych i parafialnych. Jeden z największych i najlepiej utrzymanych portali. Firma: Polskie Cmentarze Sp. z o.o.

**API:** Brak publicznego REST API. Brak dokumentacji technicznej.

**Metoda dostępu:** Formularz HTML z AJAX. Na GitHub istnieje projekt [`Cisowscy/grobonet-for-gedcom`](https://github.com/Cisowscy/grobonet-for-gedcom) (JavaScript) — scrapuje dane Grobonet i eksportuje do GEDCOM. **Precedens scraping istnieje.**

**Dane w rekordzie:**
- Imię i nazwisko
- Data urodzenia, data śmierci
- Lokalizacja grobu (sektor / rząd / numer)
- Nazwa cmentarza + miejscowość
- Opcjonalnie: zdjęcie grobu

**Kontakt:** `grobonet@polskie-cmentarze.com` — warto zapytać o warunki integracji przed scraping.

**Legalność:** Brak jawnej polityki scrapingu w ToS. Scraping możliwy przy respektowaniu robots.txt i rate limiting (min. 2s między zapytaniami).

**Ocena przydatności: 4/5** — największy zasięg, dane aktualizowane przez administratorów.

---

### 2. eCmentarze (ecmentarze.pl) ⭐⭐⭐

**Zasięg:** Ponad 2,36 mln rekordów pochowanych — potencjalnie większa baza niż Grobonet pod względem rekordów.

**API:** Brak publicznego API.

**Metoda dostępu:** Formularz HTML, scraping możliwy.

**Dane:** Imię, nazwisko, daty urodzenia/śmierci, lokalizacja grobu, zdjęcie, mapa cmentarza.

**Ocena: 3/5** — duża baza, ale mniej znana niż Grobonet. Brak precedensów open-source.

---

### 3. Mogily.pl ⭐⭐

**Zasięg:** Kilkanaście większych miast. Aplikacja mobilna z GPS lokalizacją grobów.

**API:** Brak.

**Ocena: 2/5** — mały zasięg, niszowa. Faza 3 lub pominąć.

---

### 4. Cmentarze24.pl ⭐⭐

**Wyróżnik:** Nekrologi i klepsydry — unikalne źródło dla genealogii (potwierdza datę śmierci i skład rodziny).

**API:** Brak.

**Ocena: 2/5** — warto jako uzupełnienie, ale nie jako główny rejestr grobów.

---

### 5. FindAGrave (findagrave.com — własność Ancestry) ⭐⭐⭐

**Zasięg Polski:** ~1 mln rekordów z polskich cmentarzy. Globalnie 250+ mln memoriałów.

**ToS:** **Scraping zakazany** w regulaminie. Cloudflare bot protection.

**Rekomendacja:** Tylko ręczne linkowanie URL w profilu osoby — pole `findagrave_url` w tabeli `persons`. Nie implementować automatycznej integracji.

---

### 6. BillionGraves ⭐⭐⭐

**Klucz:** Dane BillionGraves są **zindeksowane przez FamilySearch** (kolekcja `2026973`). Dostęp przez FamilySearch API (które już jest planowane w feature `registries`) jest **legalny i udokumentowany**.

**Rekomendacja:** Uwzględnić w zapytaniach FamilySearch API — brak osobnej integracji potrzebna.

---

### 7. PRADZIAD (pradziad.pl) ⭐⭐

**Co to jest:** Rejestr **metrykalny** (urodzenia, śluby, zgony), nie cmentarny. Ale pokrywa się tematycznie (zgony).

**Metoda:** Scraper formularza HTML. Już w CLAUDE.md jako "Faza 2".

**Rekomendacja:** Włączyć do istniejącego planu `registries` jako Phase 3, nie do cemetery-registries.

---

## Porównanie metod dostępu

| Rejestr | Metoda | Legalność | Trudność impl. |
|---------|--------|-----------|----------------|
| Grobonet | Python scraper + HTML parse | ⚠️ Zapytaj o ToS | Średnia |
| eCmentarze | Python scraper + HTML parse | ⚠️ Sprawdź robots.txt | Średnia |
| FindAGrave | ❌ Brak (ToS zakazuje) | ❌ Nielegalne | — |
| BillionGraves | ✅ FamilySearch API (pośrednio) | ✅ Legalne | Niska (już planowane) |
| Mogily.pl | Python scraper | ⚠️ Sprawdź ToS | Niska |

---

## Dlaczego Python scraper, nie PHP?

Z CLAUDE.md: projekt ma zaplanowany **mikroserwis Python FastAPI** (`scraper/`) jako osobny serwis. PHP nie nadaje się do long-running scrapingu z obsługą JS (Playwright). Grobonet i eCmentarze potencjalnie używają JavaScript do ładowania wyników — wymaga Playwright lub httpx z obsługą cookies.

Architektura: PHP → INSERT do `search_jobs` → Python polling → scraping → UPDATE `search_jobs` z wynikami → PHP polling przez Alpine.js co 2s.

---

## Decyzje projektowe

| Decyzja | Wybór | Powód |
|---------|-------|-------|
| Priorytet rejestru | Grobonet | Największy zasięg, precedens scraping |
| Metoda | Python scraper (`scraper/scrapers/grobonet.py`) | Zgodne z istniejącą architekturą CLAUDE.md |
| FindAGrave | URL field tylko | ToS zakazuje scraping |
| BillionGraves | Przez FamilySearch API | Legalne, bez dodatkowej pracy |
| Faza 1 | Grobonet scraper | Proof of concept |
| Faza 2 | eCmentarze | Po weryfikacji robots.txt |
| Faza 3 | Mogily.pl | Opcjonalnie, mały zasięg |

---

## Krok zerowy: Email do Grobonet

**PRZED implementacją scrapers** — wyslać mail do `grobonet@polskie-cmentarze.com`:

> Temat: Zapytanie o możliwość integracji z systemem Genealog
>
> Dzień dobry,
> Tworzymy bezpłatną aplikację genealogiczną Genealog (genealog.pl). Chcielibyśmy zintegrować wyszukiwanie osób pochowanych z Państwa systemem. Czy istnieje możliwość formalnej integracji (API key, partnership) lub czy wyrażają Państwo zgodę na niekomercyjne wyszukiwanie programatyczne z poszanowaniem rate limitów?

Jeśli zgoda → oficjalna integracja. Jeśli brak odpowiedzi (2 tygodnie) → scraping z robots.txt + rate limit 2s.

---

## Powiązania z istniejącym kodem

- `src/Services/Discovery/MatchSourceInterface.php` — Grobonet będzie implementował ten interfejs → wyszukiwanie działa w panelu Discovery
- `src/Services/Discovery/Sources/GenetykaMatchSource.php` — wzorzec do naśladowania dla `GrobonetMatchSource.php`
- `scraper/` — tu trafi `grobonet.py` (zaplanowane w CLAUDE.md)
- `search_jobs` tabela — już istnieje, cemetery search używa tego samego mechanizmu queue
- `registry_cache` tabela — cache wyników (TTL 30 dni)
