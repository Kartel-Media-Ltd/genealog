# Rejestry Zewnętrzne — Kontekst i Decyzje Architektoniczne

## Przegląd

Dokument rejestruje kluczowe decyzje podjęte przy projektowaniu feature Rejestry Zewnętrzne. Każda decyzja zawiera uzasadnienie i odrzucone alternatywy.

---

## Decyzja 1: Geneteka przez CSV dump, nie scraper

> **⚠️ NIEAKTUALNE (2026-04-07):** Weryfikacja wykazała, że PTG **nie udostępnia publicznego CSV dump** Geneteki. Decyzja została **odwrócona** — rekomendowana strategia to scraper HTTP z rate limitem (lub kontakt z zarządem PTG o uzyskanie dump'a). Patrz sekcja "Weryfikacja API kluczy" na końcu dokumentu.
>
> Treść poniżej zachowana jako historia decyzji.

### Pierwotnie wybrana opcja (NIEAKTUALNA)

Import lokalny z CSV dump udostępnianego przez PTG (Polskie Towarzystwo Genealogiczne).

### Uzasadnienie

| Kryterium | CSV dump | Scraper HTTP |
|-----------|----------|--------------|
| **Legalność** | Legalne — PTG udostępnia CSV do pobrania | Ryzyko — ToS nie zezwalają na automated scraping |
| **Prędkość wyszukiwania** | < 100ms (lokalna MySQL) | 2-10s (HTTP round-trip + parsowanie HTML) |
| **Ryzyko blokady IP** | Brak | Wysokie — bot detection, Cloudflare |
| **Dostępność** | 100% (lokalny DB) | Zależna od dostępności serwisu Geneteka |
| **Aktualność** | Dump co kilka miesięcy (wystarczające dla danych historycznych) | Real-time (zbędne dla metryk z XIX wieku) |
| **Złożoność** | CLI import script + MySQL table | Python scraper + kolejka + retry + cache |

### Dane historyczne nie wymagają real-time

Geneteka zawiera metryki kościelne i urzędowe z XVIII-XIX wieku. Dane te zmieniają się tylko przy dodaniu nowych indeksów przez wolontariuszy PTG — kilka razy w roku. Import CSV raz na kwartał jest w pełni wystarczający.

### Odrzucone alternatywy

- **Scraper HTTP na Genetece**: ryzyko prawne, blokady IP, fragile (zmiany HTML łamią scraper)
- **API Geneteki**: brak publicznego API (2025)
- **Geneteka przez Python/Playwright**: złożoność bez korzyści, skoro CSV jest dostępny

---

## Decyzja 2: FamilySearch API jako priorytet MVP

### Wybrana opcja

FamilySearch REST API z OAuth2 client credentials.

### Uzasadnienie

**Bezpłatne i oficjalne**: FamilySearch (The Church of Jesus Christ of Latter-day Saints) udostępnia API bez opłat dla aplikacji genealogicznych. Oficjalna rejestracja aplikacji na developer.familysearch.org.

**Bogate dane dla Polski**: FamilySearch posiada największą zdigitalizowaną kolekcję polskich metryk (parafie, USC, spisy ludności). Projekt indeksowania polskich archiwów trwa od lat 90.

**Stabilne API**: REST API z wersjonowaniem, dokumentacja, sandbox dla testów. Nie zmienia się bez powiadomienia.

**Standard branżowy**: FamilySearch to de facto standard wśród aplikacji genealogicznych. Użytkownicy oczekują integracji.

### Odrzucone alternatywy

- **MyHeritage API**: $600-2000/rok dla komercyjnego dostępu. Nieopłacalne dla MVP.
- **Ancestry API**: dostępne tylko dla partnerów strategicznych (korporacje), nie dla indie developerów.
- **Wikidata SPARQL**: dobre dla znanych osób historycznych, słabe dla genealogii (brak metryk parafialnych).

---

## Decyzja 3: Nie MyHeritage/Ancestry API

### Odrzucona opcja

Integracja z płatnymi serwisami genealogicznymi.

### Uzasadnienie

| Serwis | Koszt API | Dostęp |
|--------|-----------|--------|
| MyHeritage | $600-2000/rok | Wymagana umowa partnerska |
| Ancestry | Wyłącznie partnerzy korporacyjni | Niedostępne dla indie dev |
| FindMyPast | Płatne, brak publicznego API | - |

Koszt i bariery dostępu dyskwalifikują te serwisy dla MVP. Strategia: FamilySearch (darmowy) + lokalna Geneteka + Archiwa Państwowe pokrywają >90% potrzeb polskich użytkowników bez żadnych kosztów.

---

## Decyzja 4: Synchroniczne wywołania API dla MVP

### Wybrana opcja

`SearchService::dispatch()` wywołuje serwisy rejestrów synchronicznie — wyniki w tej samej sesji PHP.

### Uzasadnienie

**Uproszczenie architektury**: asynchroniczna kolejka (Redis, cron, workers) to ~3 tygodnie dodatkowej pracy. Dla MVP z 2-5s czasem odpowiedzi API nie ma sensu.

**Polling Alpine.js jest gotowy**: frontend jest napisany tak, żeby obsługiwać oba tryby. Jeśli `status=done` przy renderowaniu strony → wyniki od razu. Jeśli `status=pending` → polling. Migracja do async w przyszłości nie wymaga zmian w frontendzie.

**Typowe czasy odpowiedzi**:
- Geneteka: < 100ms (lokalny MySQL z indeksem)
- Szukaj w Archiwach: 1-3s
- FamilySearch: 1-5s (zależnie od złożoności query)

Żaden z rejestrów MVP nie wymaga >5s. PHP `max_execution_time = 30s` jest wystarczające.

### Kiedy warto przejść na async

- Dodanie scraperów Python (Grobonet, PRADZIAD) — mogą trwać 15-60s
- Wiele rejestrów równolegle (fan-out queries)
- Duże wolumeny użytkowników (>100 concurrent searches)

### Odrzucone alternatywy

- **Python FastAPI + kolejka**: właściwe rozwiązanie dla Fazy 2 scraperów, nadmierne dla MVP
- **Redis job queue**: dodatkowy serwis do zarządzania, zbędny przy synchronicznych API
- **Cron worker**: skomplikowany deploy, problemy z synchronizacją sesji

---

## Decyzja 5: Python scraper (Grobonet, PRADZIAD) → Faza 2

### Odroczona opcja

Scraper Grobonet i PRADZIAD jako Python FastAPI mikroserwis.

### Uzasadnienie odroczenia

**Złożoność techniczna**: Grobonet i PRADZIAD to strony z formularzami HTML, sesja cookies, brak REST API. Wymagają:
- Obsługi JavaScript (Playwright lub Selenium)
- Zarządzania sesją/cookies
- Parsowania niestandardowego HTML
- Retry logic przy blokadach
- Osobnego deployu (Docker, sieć między kontenerami)

**Lepsze opcje przed scraperem**: FamilySearch + Archiwa Państwowe + Geneteka pokryją większość przypadków użycia. Scraper Grobonet (cmentarze) i PRADZIAD (akta stanu cywilnego XIX w.) to uzupełnienie, nie rdzeń MVP.

**Ryzyko prawne**: Scraping bez zgody właściciela serwisu może naruszać ToS. Przed implementacją należy zweryfikować regulaminy.

### Plan Fazy 2

```
genealog/
└── scraper/
    ├── main.py           # FastAPI endpoint
    ├── scrapers/
    │   ├── base.py       # BaseScraper (retry, rate limit, cache)
    │   ├── grobonet.py
    │   └── pradziad.py
    └── requirements.txt
```

PHP komunikuje się z mikrousługą przez HTTP (opcja A) lub przez polling tabeli `search_jobs` (opcja B — prostsze, rekomendowane).

---

## Decyzja 6: Tabela search_jobs jako fundament

### Istniejąca infrastruktura

Tabela `search_jobs` jest już zdefiniowana w schemacie migracji. To celowy fundament — feature rejestrów był planowany od początku projektowania bazy danych.

```sql
search_jobs (
  id, user_id FK, registry VARCHAR(50),
  params JSON,
  status ENUM('pending','running','done','failed'),
  results JSON,
  error TEXT,
  created_at, finished_at
)
```

### Korzyści gotowego schematu

- **Audytowalność**: każde zapytanie użytkownika jest logowane z `user_id`, `registry`, `params`
- **Debugowanie**: błędy API (status=failed) z treścią błędu w `error`
- **Przyszła analityka**: najpopularniejsze rejestry, częstość wyszukiwań, średni czas odpowiedzi
- **Async-ready**: gdy przyjdzie czas na kolejkę — tabela jest już odpowiednia strukturą

### Powiązana tabela registry_cache

```sql
registry_cache (
  id, registry, query_hash CHAR(64),
  results JSON, cached_at,
  UNIQUE KEY (registry, query_hash)
)
```

Cache z TTL 30 dni eliminuje redundantne wywołania API dla identycznych zapytań. Szczególnie ważne dla FamilySearch (rate limity) i Archiwów Państwowych.

---

## Harmonogram implementacji (szacunki)

| Faza | Zadanie | Szacunek |
|------|---------|----------|
| 1 | Infrastruktura (Controller, Service, Repo, Routing) | 1 dzień |
| 2 | Geneteka (migracja + import CSV + GenetykaService) | 1-2 dni |
| 3 | Szukaj w Archiwach (ArchivesService + testy) | 1 dzień |
| 4 | FamilySearch (OAuth2 + FamilySearchService + testy) | 2 dni |
| 5 | Widok wyszukiwania (Alpine.js, formularz, karty wyników) | 1-2 dni |
| 6 | Weryfikacja E2E + poprawki | 1 dzień |
| **Razem MVP** | | **~8 dni roboczych** |
| Faza 2 | Python scraper (Grobonet, PRADZIAD) | ~2 tygodnie |

---

## Otwarte pytania

1. **FamilySearch Sandbox → Produkcja**: certyfikacja w Compatible Solution Program — wymaga legal entity (działalność lub NGO). Czas zatwierdzenia: nieznany, prawdopodobnie tygodnie/miesiące.
2. **FamilySearch `client_credentials`**: NIE jest standardowo dostępny. Trzeba zdecydować: Authorization Code (3-legged OAuth) vs special permission od dev support.
3. **PTG dump**: czy zarząd PTG udostępni CSV dump Geneteki na prośbę? (`zarzad@genealodzy.pl`)
4. **NAC API**: czy Narodowe Archiwum Cyfrowe ma jakikolwiek programatyczny dostęp do `szukajwarchiwach.gov.pl`? (`szukajwarchiwach@nac.gov.pl`)
5. **Lokalizacja cache**: gdzie przechowywać scrapowane wyniki? `registry_cache` (już zaplanowane) z TTL 30 dni.

---

## Weryfikacja API kluczy — 2026-04-07

Zweryfikowano stan dostępności API dla 3 rejestrów. **Pierwotny plan zawierał błędne założenia.**

### FamilySearch — DARMOWE z zastrzeżeniami

- ✅ API jest darmowe ([źródło](https://developers.familysearch.org/main/docs/getting-started))
- ✅ Sandbox/Integration dostępny od razu po rejestracji (test data)
- ⚠️ Produkcja wymaga **certyfikacji** w Compatible Solution Program
- ⚠️ Tylko **legal, registered business or non-profit organization** może być zweryfikowane
- ⚠️ `grant_type=client_credentials` **nie jest standardowo dostępny** — wymaga special permission od `devsupport@familysearch.org`. Standardowy flow to **Authorization Code (3-legged OAuth)** z user consent
- ❓ Brak publicznej dokumentacji rate limitów

### Szukaj w Archiwach — DARMOWE, ALE BRAK API

- ✅ Portal darmowy (3.5M skanów dokumentów)
- ❌ **NIE MA publicznego REST API** — tylko interfejs HTML do przeglądania
- ❌ Pierwotnie planowany endpoint `/api/szukaj` **nie istnieje**
- 🔧 Alternatywy: kontakt z NAC, scraper HTTP, lub pominięcie w MVP
- 📧 Kontakt: `szukajwarchiwach@nac.gov.pl`

### Geneteka (PTG) — DARMOWE, ALE BRAK CSV DUMP

- ✅ Bezpłatny dostęp do bazy 47M+ wpisów
- ❌ **Brak oficjalnego CSV dump** publicznie udostępnianego (pierwotny plan błędny)
- 🔧 Alternatywy: scraper HTTP (rate limit 1 req/s), kontakt z PTG o dump
- 📧 Kontakt: `zarzad@genealodzy.pl`

### Wnioski dla MVP

| Rejestr | Strategia MVP | Faza |
|---------|---------------|------|
| **FamilySearch** | Sandbox + Authorization Code grant | Faza 4 (z user consent flow) |
| **Geneteka** | Scraper HTTP (User-Agent + 1 req/s) | Faza 2 (zamiast importu CSV) |
| **Szukaj w Archiwach** | **POMINIĘTE** — czekać na odpowiedź NAC | Faza 3 → odłożona |

**Korekta architektury:** zamiast 3 rejestrów w MVP — implementujemy 2 (FamilySearch Sandbox + Geneteka scraper), Szukaj w Archiwach po kontakcie z NAC.
