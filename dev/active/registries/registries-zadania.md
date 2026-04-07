# Rejestry Zewnętrzne — Checklist Zadań

> Oznaczenia: `[ ]` do zrobienia, `[x]` ukończone, `[USER]` wymaga ręcznej akcji użytkownika

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

## Faza 2: Geneteka — import CSV i wyszukiwanie lokalne

### 2.1 Migracja

- [ ] [USER] Uruchomić migrację `migrations/005_registries.sql`:
  ```bash
  source .env.local && docker exec -i mariadb_docker mariadb \
    -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
    < migrations/005_registries.sql
  ```
- [ ] Zweryfikować strukturę tabeli: `DESCRIBE geneteka_records;`

### 2.2 Import CSV

- [ ] [USER] Pobrać CSV dump z Geneteki: https://geneteka.genealodzy.pl (sekcja "Pobierz dane")
- [ ] Utworzyć `bin/import-geneteka.php` — skrypt CLI do importu:
  - [ ] Parsowanie CSV (PHP `fgetcsv`)
  - [ ] Batch INSERT — paczki po 500 rekordów (`INSERT INTO ... VALUES (...),(...),...`)
  - [ ] Obsługa kodowania: UTF-8 lub konwersja z CP1250 (`mb_convert_encoding`)
  - [ ] Progress bar w CLI (licznik rekordów)
  - [ ] Opcja `--truncate` do pełnego reimportu
- [ ] Przetestować import na próbce 1000 rekordów
- [ ] Zindeksować tabelę po imporcie: sprawdzić `SHOW INDEX FROM geneteka_records`

### 2.3 GenetykaService

- [ ] Utworzyć `src/Services/Registries/GenetykaService.php`
  - [ ] Metoda `search(array $params): array`
  - [ ] Warunki: `surname LIKE ?%`, `given_name LIKE ?%`, `birth_year BETWEEN ? AND ?`, `region LIKE %?%`
  - [ ] Przynajmniej jeden warunek wymagany (ochrona przed empty query → pełny scan)
  - [ ] Limit 100 wyników
  - [ ] Format wyjściowy: `['source' => 'Geneteka', 'surname', 'given_name', 'birth_year', ...]`

---

## Faza 3: Szukaj w Archiwach — REST API

### 3.1 Konfiguracja

- [ ] [USER] Zarejestrować się na https://www.szukajwarchiwach.gov.pl i uzyskać klucz API
- [ ] [USER] Dodać do `.env.local`:
  ```
  ARCHIVES_API_KEY=twoj_klucz_api
  ```
- [ ] Wczytać klucz w `config/config.php`: `getenv('ARCHIVES_API_KEY')`

### 3.2 ArchivesService

- [ ] Utworzyć `src/Services/Registries/ArchivesService.php`
  - [ ] Wstrzyknąć `$apiKey` przez konstruktor (z `config`)
  - [ ] Metoda `search(array $params): array`
  - [ ] cURL GET z nagłówkiem `X-API-Key` i `User-Agent`
  - [ ] Timeout: 15s
  - [ ] Obsługa błędów HTTP (4xx, 5xx): rzucić wyjątek, złapać w SearchService
  - [ ] Parsowanie JSON response → znormalizowany format wyników
  - [ ] Przetestować z Postmanem lub curl przed integracją

---

## Faza 4: FamilySearch — OAuth2 + REST API

### 4.1 Rejestracja aplikacji

- [ ] [USER] Zarejestrować aplikację: https://www.familysearch.org/developers/
  - Typ: "Server-side Web App" lub "Client Credentials"
  - Środowisko: najpierw **Sandbox** (`https://api-integ.familysearch.org`)
- [ ] [USER] Dodać do `.env.local`:
  ```
  FAMILYSEARCH_CLIENT_ID=twoje_client_id
  FAMILYSEARCH_CLIENT_SECRET=twoje_client_secret
  FAMILYSEARCH_ENV=sandbox
  ```

### 4.2 FamilySearchService

- [ ] Utworzyć `src/Services/Registries/FamilySearchService.php`
  - [ ] `getToken(): string` — OAuth2 client credentials flow
    - [ ] Cache tokenu w pamięci (property) dla jednego requesta
    - [ ] Obsługa `expires_in` — odnawianie przed wygaśnięciem (bufor 60s)
  - [ ] `search(array $params): array`
    - [ ] Wywołanie `/platform/tree/persons` z query param `q`
    - [ ] Nagłówki: `Authorization: Bearer {token}`, `Accept: application/json`
    - [ ] Parsowanie struktury `persons[].names[].nameForms[].parts[]`
  - [ ] Przetestować na Sandbox: wyniki dla popularnych polskich nazwisk (Kowalski, Nowak)

### 4.3 Przejście na produkcję

- [ ] [USER] Po zatwierdzeniu przez FamilySearch: zmienić `FAMILYSEARCH_ENV=production`
- [ ] Zaktualizować BASE_URL w serwisie na `https://api.familysearch.org`

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
- `migrations/005_registries.sql` dodaje tylko `geneteka_records`
- Python scraper (Grobonet, PRADZIAD) → osobna gałąź feature, nie MVP
- FamilySearch Sandbox ma ograniczone dane — nie testować na produkcji przed zatwierdzeniem
