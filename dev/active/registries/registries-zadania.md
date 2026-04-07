# Rejestry Zewnętrzne — Checklist Zadań

> Oznaczenia: `[ ]` do zrobienia, `[x]` ukończone, `[USER]` wymaga ręcznej akcji użytkownika

---

## ⚠️ Status API kluczy (2026-04-07)

| Rejestr | Koszt | Status | Strategia |
|---------|-------|--------|-----------|
| **Geneteka** | DARMOWE | Brak oficjalnego API/CSV dump | Scraper HTTP (rate limit 1 req/s) lub kontakt z PTG |
| **FamilySearch** | DARMOWE | Sandbox: instant | MVP: tylko Sandbox; Produkcja wymaga certyfikacji jako legal entity |
| **Szukaj w Archiwach** | DARMOWE | **Brak publicznego REST API** | Pominąć w MVP, ewentualnie scraper |

**Implikacje dla planu:**
- ❌ Pierwotny plan zakładał `https://www.szukajwarchiwach.gov.pl/api/szukaj` — endpoint **nie istnieje**
- ❌ Pierwotny plan zakładał oficjalny CSV dump Geneteki — **nie ma takiego**
- ⚠️ FamilySearch `client_credentials` grant **nie jest standardowo dostępny** — wymaga special permission lub Authorization Code (3-legged OAuth z user consent)
- ✅ Wszystkie 3 rejestry są darmowe (po przezwyciężeniu barier dostępu)

**Rekomendowana implementacja MVP:**
1. **FamilySearch Sandbox** — działa od razu, dane testowe wystarczają do prototypu
2. **Geneteka scraper** — z User-Agent i rate limitem
3. **Szukaj w Archiwach** — pomijamy do czasu kontaktu z NAC (`szukajwarchiwach@nac.gov.pl`)

---

## ⚠️ Konflikt numeracji migracji

Pierwotny plan przewidywał `migrations/005_registries.sql`, ale ten numer został już użyty przez `005_invitations_indexes.sql`.

**Nowa numeracja: `migrations/006_registries.sql`**

---

## Faza 1: Infrastruktura

Fundament — SearchController, SearchService, SearchRepository, routing, interfejs.

### 1.1 Interface i routing

- [ ] Utworzyć `src/Services/Registries/RegistryInterface.php` z metodą `search(array $params): array`
- [ ] Dodać routes do `src/Core/Router.php`:
  - `GET /search` → `SearchController::showSearch`
  - `POST /search` → `SearchController::processSearch`
  - `GET /api/search-jobs/{id}` → `SearchController::jobStatus`

### 1.2 SearchRepository

- [ ] Utworzyć `src/Repositories/SearchRepository.php`
  - [ ] `createJob(userId, registry, params, status, results): int`
  - [ ] `findJob(id, ?userId): ?array`
  - [ ] `updateJobStatus(id, status): void`
  - [ ] `updateJobDone(id, resultsJson): void`
  - [ ] `updateJobFailed(id, error): void`
  - [ ] `findCache(registry, queryHash): ?array` — TTL 30 dni
  - [ ] `saveCache(registry, queryHash, resultsJson): void` — INSERT ... ON DUPLICATE KEY UPDATE

### 1.3 SearchService

- [ ] Utworzyć `src/Services/SearchService.php`
  - [ ] `dispatch(userId, registry, params): array` — synchroniczne dla MVP
  - [ ] `getJobStatus(jobId, ?userId): ?array`
  - [ ] `buildQueryHash(registry, params): string` — `sha256(registry + json(params))`
  - [ ] Walidacja: registry musi być w liście dozwolonych (`geneteka|familysearch|archives`)

### 1.4 SearchController

- [ ] Utworzyć `src/Controllers/SearchController.php`
  - [ ] `showSearch(Request): void` — pobiera `?job=` z query string
  - [ ] `processSearch(Request): void` — weryfikacja CSRF, walidacja params, dispatch, redirect
  - [ ] `jobStatus(Request, int $id): void` — JSON response
  - [ ] Zabezpieczenie `jobStatus`: tylko zalogowani użytkownicy (middleware lub inline check)

---

## Faza 2: Geneteka — scraper HTTP (wcześniej: CSV dump)

> **Zmiana strategii:** PTG nie udostępnia oficjalnego CSV dump. Zamiast importu lokalnego implementujemy scraper HTTP z respektowaniem rate limitu.

### 2.1 Migracja

- [ ] [USER] Uruchomić migrację `migrations/006_registries.sql`:
  ```bash
  source .env.local && docker exec -i mariadb_docker mariadb \
    -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
    < migrations/006_registries.sql
  ```
- [ ] Zweryfikować strukturę tabeli: `DESCRIBE geneteka_records;`
  (tabela do cache'owania scrapowanych wyników, nie pełnego dumpu)

### 2.2 GenetykaService — scraper HTTP

- [ ] Utworzyć `src/Services/Registries/GenetykaService.php` (lub `GenetekaScraper`)
  - [ ] Metoda `search(array $params): array`
  - [ ] cURL GET na `https://geneteka.genealodzy.pl/index.php?op=se&...`
  - [ ] **User-Agent identyfikujący**: `Genealog.pl Research Tool/1.0 (kontakt@genealog.pl)`
  - [ ] **Rate limit: 1 request/s** (sleep w PHP lub queue tabela)
  - [ ] Parsowanie HTML wyników przez DOMDocument lub regex (alternatywa: instalacja `symfony/dom-crawler`)
  - [ ] Limit 100 wyników z jednego zapytania
  - [ ] Format wyjściowy: `['source' => 'Geneteka', 'surname', 'given_name', 'birth_year', 'parish', 'region', 'document_type', 'year']`
  - [ ] Cache 30 dni w `registry_cache` (już w SearchService)

### 2.3 [USER] Kontakt z PTG (opcjonalnie, równolegle)

- [ ] [USER] Wysłać maila do `zarzad@genealodzy.pl` z prośbą o oficjalny dump CSV (alternatywa scrapera)
- [ ] Jeśli PTG się zgodzi → zaimplementować `bin/import-geneteka.php` jako CLI batch import zamiast scrapera

### 2.4 [USER] Opcjonalna alternatywa: Geneszukacz

- [ ] [USER] Zweryfikować czy `https://geneszukacz.genealodzy.pl` ma API lub łatwiejsze do scrapowania endpointy
- [ ] Geneszukacz zawiera mniej danych niż Geneteka, ale może być prostszy w integracji

---

## Faza 3: Szukaj w Archiwach — POMINIĘTE w MVP

> **⚠️ Pierwotny plan był błędny.** Endpoint `https://www.szukajwarchiwach.gov.pl/api/szukaj` **nie istnieje publicznie**. Portal udostępnia tylko interfejs HTML do przeglądania (3.5M skanów) bez API.
>
> **Rekomendacja:** pominąć ten rejestr w MVP. Po zakończeniu reszty feature'a:

### 3.1 [USER] Kontakt z NAC (Narodowe Archiwum Cyfrowe)

- [ ] [USER] Wysłać maila do `szukajwarchiwach@nac.gov.pl` z zapytaniem o:
  - Możliwość uzyskania dostępu programatycznego (API, OAI-PMH, SRU)
  - Warunki licencyjne dla bulk scrapingu
  - Czy istnieje partnership program dla aplikacji genealogicznych
- [ ] [USER] W razie pozytywnej odpowiedzi: udokumentować warunki dostępu

### 3.2 Alternatywa: Scraper HTML (jeśli NAC odmówi API)

- [ ] Zaimplementować `ArchivesScraper` analogicznie do GenetekaScraper
  - cURL GET na `https://www.szukajwarchiwach.gov.pl/wyszukiwarka?q=...`
  - Parsowanie HTML przez DOMDocument
  - Rate limit 1 req/s, User-Agent identyfikujący
  - **PRZED IMPLEMENTACJĄ:** sprawdzić `https://www.szukajwarchiwach.gov.pl/robots.txt` czy nie blokuje botów
  - **Ryzyko:** NAC może zablokować IP — używać tylko gdy NAC nie odpowie

### 3.3 Alternatywa: Genealogia w Archiwach (powiązany serwis)

- [ ] [USER] Zweryfikować `https://www.genealogiawarchiwach.pl` — czy ma API lub jest łatwiejszy do integracji
- [ ] [USER] Sprawdzić `https://archiwa.gov.pl/en/search-in-archives/databases/` — lista innych baz państwowych (PRADZIAD, ELA, IZA)

---

## Faza 4: FamilySearch — OAuth2 + REST API

> **Uwaga o `client_credentials`:** ten grant type **NIE jest standardowo dostępny** w FamilySearch API. Wymaga special permission od `devsupport@familysearch.org`. Rekomendowany dla aplikacji webowej jest **Authorization Code grant (3-legged OAuth)** z user consent.
>
> Alternatywa: użyć Authorization Code dla MVP (user się loguje przez FamilySearch przy pierwszym wyszukiwaniu).

### 4.1 Rejestracja aplikacji (Sandbox)

- [ ] [USER] Utworzyć konto deweloperskie: https://www.familysearch.org/developers/
- [ ] [USER] Zarejestrować aplikację — wybrać "Server-side Web App" lub "Browser-based App"
- [ ] [USER] Sandbox/Integration jest dostępne **od razu** po rejestracji — zawiera tylko test data
- [ ] [USER] Dodać do `.env.local`:
  ```
  FAMILYSEARCH_CLIENT_ID=twoje_app_key
  FAMILYSEARCH_CLIENT_SECRET=twoj_app_secret
  FAMILYSEARCH_ENV=sandbox
  FAMILYSEARCH_REDIRECT_URI=http://localhost:8002/auth/familysearch/callback
  ```
- [ ] Wczytać klucze w `config/config.php` przez `getenv()`

### 4.2 [USER] Decyzja: client_credentials vs Authorization Code

**Opcja A — Authorization Code (rekomendowana, działa od razu):**
- [ ] [USER] Zaakceptować że użytkownik musi się zalogować do FamilySearch przy pierwszym wyszukiwaniu
- [ ] Implementacja dłuższa — wymaga callback endpoint, refresh tokens, session storage
- [ ] Działa od razu po rejestracji aplikacji

**Opcja B — Client Credentials (wymaga special permission):**
- [ ] [USER] Wysłać maila do `devsupport@familysearch.org` z prośbą o włączenie `client_credentials` grant
- [ ] [USER] Czekać na zatwierdzenie (czas nieznany)
- [ ] Implementacja prostsza — jeden token dla wszystkich userów
- [ ] **Ryzyko:** FamilySearch może odmówić jeśli aplikacja nie jest legal entity

### 4.3 FamilySearchService

- [ ] Utworzyć `src/Services/Registries/FamilySearchService.php`
  - [ ] `getToken(): string` — OAuth2 flow (zależnie od wybranej opcji A/B)
    - [ ] Cache tokenu w sesji lub DB (`familysearch_tokens` per user)
    - [ ] Obsługa `expires_in` — refresh token przed wygaśnięciem (bufor 60s)
  - [ ] `search(array $params): array`
    - [ ] Wywołanie `/platform/tree/persons` (Sandbox: `https://api-integ.familysearch.org`)
    - [ ] Nagłówki: `Authorization: Bearer {token}`, `Accept: application/json`, `User-Agent`
    - [ ] Parsowanie struktury `persons[].names[].nameForms[].parts[]`
  - [ ] Timeout: 15s, retry 3x z exponential backoff
  - [ ] Przetestować na Sandbox: wyniki dla popularnych polskich nazwisk (Kowalski, Nowak)

### 4.4 Przejście na produkcję — Compatible Solution Program

> **WAŻNE:** Do produkcji wymagana jest **certyfikacja** przez FamilySearch. Tylko **legal, registered business or non-profit organization** może być zweryfikowane.

- [ ] [USER] Zarejestrować Genealog jako legal entity (działalność gospodarcza, NGO, etc.)
- [ ] [USER] Aplikować do "Compatible Solution Program": https://www.familysearch.org/innovate/api-compatible-checklists
- [ ] [USER] Przejść compatibility review przez FamilySearch (code review + testy)
- [ ] [USER] Po zatwierdzeniu: zmienić `FAMILYSEARCH_ENV=production`
- [ ] Zaktualizować BASE_URL w serwisie na `https://api.familysearch.org`
- [ ] Sprawdzić czy production rate limity wymuszają zmianę implementacji

---

## Faza 5: Widok wyszukiwania

### 5.1 Strona wyszukiwania

- [ ] Utworzyć `src/Views/pages/search/index.php`
  - [ ] Formularz: pola `last_name`, `first_name`, `birth_year`, `region`, `registry` (radio)
  - [ ] Token CSRF w hidden input
  - [ ] Alpine.js `x-data="searchResults(jobId, status)"` + `x-init="init()"`
  - [ ] Polling: `setInterval(() => checkStatus(), 2000)` — zatrzymuje się gdy `done` lub `failed`
  - [ ] Warunek `x-show` dla stanów: loading, done, failed

### 5.2 Komponenty

- [ ] Utworzyć `src/Views/molecules/registry-result.php` — karta pojedynczego wyniku:
  - Nazwa rejestru (badge)
  - Imię, nazwisko, rok urodzenia, miejsce
  - Link do źródła (jeśli dostępny — FamilySearch, Archiwa)
  - Opcjonalnie: przycisk "Dodaj do drzewa" (Faza 6)
- [ ] Zweryfikować responsywność na mobile (Tailwind breakpoints)
- [ ] Atom `atoms/spinner.php` — jeśli jeszcze nie istnieje

### 5.3 Nawigacja

- [ ] Dodać link "Szukaj w rejestrach" do `organisms/header.php` lub menu nawigacyjnego

---

## Faza 6: Weryfikacja E2E

### 6.1 Testy manualne

- [ ] Geneteka: wyszukanie po nazwisku → wyniki z lokalnej tabeli
- [ ] Geneteka: brak wyników → komunikat "Brak wyników"
- [ ] Szukaj w Archiwach: zapytanie → wyniki z API
- [ ] FamilySearch (Sandbox): zapytanie → wyniki z Sandbox API
- [ ] Cache: to samo zapytanie drugi raz → bez nowego requestu do API (sprawdzić w `registry_cache`)
- [ ] Błąd API (wyłączone wifi): status `failed`, komunikat o błędzie
- [ ] CSRF: POST bez tokenu → 403

### 6.2 Testy wydajności

- [ ] Import 100k rekordów Geneteki — czas importu < 60s
- [ ] Zapytanie Geneteka po indeksowanym nazwisku — czas < 100ms
- [ ] Zapytanie FamilySearch API — czas < 5s (timeout 15s)

### 6.3 Weryfikacja bezpieczeństwa

- [ ] Brak klucza API w kodzie (grep `ARCHIVES_API_KEY` w `src/` — powinno być puste)
- [ ] Brak klucza API w `git log` (weryfikacja że `.env.local` jest w `.gitignore`)
- [ ] SQL injection test: `' OR '1'='1` w polu nazwiska → brak wyników (PDO prepared statements)
- [ ] Rate limiting: 11 requestów z tego samego IP w ciągu 1 minuty → 429

---

## Notatki

- `search_jobs` i `registry_cache` są już w schemacie (migrations/001 lub analogiczna) — nie duplikować
- `migrations/006_registries.sql` dodaje tylko `geneteka_records` (numer 005 zajęty)
- Python scraper (Grobonet, PRADZIAD) → osobna gałąź feature, nie MVP
- FamilySearch Sandbox ma ograniczone dane — nie testować na produkcji przed zatwierdzeniem
