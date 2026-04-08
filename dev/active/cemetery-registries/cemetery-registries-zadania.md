# Rejestry Cmentarne — Checklist Zadań

> Oznaczenia: `[ ]` do zrobienia, `[x]` ukończone, `[USER]` wymaga ręcznej akcji

---

## Status rejestrów (2026-04-08)

| Rejestr | Zasięg | API | Metoda | Priorytet |
|---------|--------|-----|--------|-----------|
| **Grobonet** | 600+ cmentarzy PL | ❌ Brak | Python scraper | 🔴 Faza 1 |
| **eCmentarze** | 2,36M rekordów | ❌ Brak | Python scraper | 🟡 Faza 2 |
| **BillionGraves** | ~1M PL | ✅ Via FamilySearch | Istniejący plan `registries` | — |
| **FindAGrave** | ~1M PL | ❌ ToS zakazuje | URL field tylko | 🔵 Faza 3 |
| **Mogily.pl** | Kilka miast | ❌ Brak | Python scraper | 🟢 Faza 4 opcjonalnie |

---

## ⚠️ Krok zerowy — PRZED implementacją

- [ ] `[USER]` Wysłać email do `grobonet@polskie-cmentarze.com` z pytaniem o możliwość integracji:
  > "Tworzymy bezpłatną aplikację genealogiczną Genealog. Czy istnieje możliwość integracji programatycznej (API) lub wyrażają Państwo zgodę na niekomercyjne wyszukiwanie z respektowaniem rate limitów?"
- [ ] `[USER]` Sprawdzić `https://grobonet.com/robots.txt` — zanotować czy `/index.php` jest zablokowany
- [ ] `[USER]` Sprawdzić `https://www.ecmentarze.pl/robots.txt` — zanotować wynik
- [ ] `[USER]` Otworzyć DevTools → Network na `grobonet.com` podczas wyszukiwania — zidentyfikować:
  - URL zapytania (GET/POST, endpoint)
  - Parametry (imie, nazwisko, itp.)
  - Struktura HTML tabeli wyników (selektory CSS)
- [ ] `[USER]` Poczekać 2 tygodnie na odpowiedź Grobonet. Jeśli brak odpowiedzi → kontynuuj scraping z robots.txt + rate limit 2s

---

## Faza 1: Grobonet — Python scraper

### 1.1 Python scraper

- [ ] Upewnić się że `scraper/` directory istnieje z `BaseScraper` (`scraper/scrapers/base.py`)
- [ ] Utworzyć `scraper/scrapers/grobonet.py`:
  - [ ] Klasa `GrobonetScraper(BaseScraper)`
  - [ ] `RATE_LIMIT = 2.0` (ostrożniej niż standardowe 1s)
  - [ ] `BASE_URL = 'https://grobonet.com/index.php'`
  - [ ] Metoda `_do_search(last_name, first_name, birth_year, region)` → GET z params
  - [ ] `_parse_results(soup)` — parsowanie HTML (UWAGA: selektory wymagają inspekcji DOM — zanotować z Fazy 0)
  - [ ] Format wyniku: `{'source', 'first_name', 'last_name', 'birth_year', 'death_year', 'cemetery', 'grave_location', 'url'}`
  - [ ] Limit 50 wyników na zapytanie
- [ ] Dodać `GrobonetScraper` do `scraper/main.py` jako dostępny registry
- [ ] Test lokalny: `python -c "from scrapers.grobonet import GrobonetScraper; ..."` z popularnym polskim nazwiskiem

### 1.2 PHP — config

- [ ] Dodać do `config/config.php`:
  ```php
  define('GROBONET_ENABLED', (bool)($_ENV['GROBONET_ENABLED'] ?? false));
  ```
- [ ] Dodać do `.env.local` (zakomentowane):
  ```dotenv
  # GROBONET_ENABLED=false  # włącz gdy scraper gotowy
  ```

### 1.3 PHP — GrobonetService

- [ ] Utworzyć `src/Services/Registries/GrobonetService.php` implementujący `RegistryInterface`
  - [ ] `search(array $params): array` — sprawdza cache → jeśli miss: INSERT `search_jobs` z `registry='grobonet'`
  - [ ] Parametry: `last_name` (wymagane), `first_name` (opcjonalne), `birth_year` (opcjonalne)
  - [ ] Pobieranie wyników z `registry_cache` gdy TTL nie wygasł (30 dni)
  - [ ] Zwraca `['job_id' => X, 'cached' => bool, 'results' => []]`

### 1.4 PHP — GrobonetMatchSource

- [ ] Utworzyć `src/Services/Discovery/Sources/GrobonetMatchSource.php` implementujący `MatchSourceInterface`
  - [ ] `getName(): string` → `'grobonet'`
  - [ ] `isAvailable(): bool` → `defined('GROBONET_ENABLED') && GROBONET_ENABLED`
  - [ ] `getTimeoutSeconds(): int` → `15`
  - [ ] `search(SearchCriteria, SearchContext): array<MatchResult>` — wrapper na `GrobonetService::search()`
  - [ ] Wyniki jako `MatchResult` z `sourceType='external'`, `externalUrl` ustawiony
- [ ] Zarejestrować w `public/index.php`:
  ```php
  use App\Services\Discovery\Sources\GrobonetMatchSource;
  $matchRegistry->register(new GrobonetMatchSource($db, $searchRepo));
  ```

### 1.5 PHP — integracja z `/search`

- [ ] Dodać `Grobonet` jako opcję w formularzu `src/views/pages/search/index.php`
  - [ ] Checkbox `registry[]=grobonet` z opisem "Grobonet — cmentarze komunalne i parafialne"
  - [ ] Widoczny tylko gdy `GROBONET_ENABLED=true`
- [ ] W `SearchService::dispatch()` → obsłużyć `registry='grobonet'` → wywołać `GrobonetService::search()`
- [ ] Komponent `molecules/cemetery-result.php`:
  - [ ] Wyświetla: imię, nazwisko, daty, nazwa cmentarza, lokalizacja grobu, link Grobonet
  - [ ] Badge "Grobonet" (jak inne rejestry)

### 1.6 Testy

- [ ] Test manualny: wyszukanie "Kowalski" na stronie `/search` z zaznaczonym Grobonet
- [ ] Weryfikacja cache: to samo zapytanie 2× → drugie z cache (sprawdzić `registry_cache` w DB)
- [ ] Weryfikacja `isAvailable()=false` gdy `GROBONET_ENABLED=false` → sekcja ukryta

---

## Faza 2: eCmentarze — Python scraper

- [ ] `[USER]` Zweryfikować `robots.txt` i strukturę HTML (analogicznie do Grobonet Faza 0)
- [ ] Utworzyć `scraper/scrapers/ecmentarze.py` (analogicznie do `grobonet.py`)
  - [ ] `BASE_URL = 'https://www.ecmentarze.pl/wyszukaj-pochowanego'`
  - [ ] Parsowanie wyników (inspekcja DOM wymagana)
- [ ] Dodać `config/config.php`: `ECMENTARZE_ENABLED`
- [ ] Utworzyć `src/Services/Registries/eCmentarzeService.php`
- [ ] Dodać do formularza `/search` jako opcja

---

## Faza 3: FindAGrave — pole URL w profilu osoby

> Scraping zakazany przez ToS. Implementujemy tylko ręczne linkowanie.

### 3.1 Migracja

- [ ] Utworzyć `migrations/016_cemetery_registries.sql`:
  ```sql
  ALTER TABLE persons
    ADD COLUMN findagrave_url VARCHAR(500) NULL DEFAULT NULL
    COMMENT 'Ręczne linkowanie do FindAGrave (scraping zakazany przez ToS)'
    AFTER photo_path;
  ```
- [ ] `[USER]` Uruchomić migrację:
  ```bash
  source .env.local && docker exec -i mariadb_docker mariadb \
    -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME \
    < migrations/016_cemetery_registries.sql
  ```

### 3.2 Formularz edycji osoby

- [ ] Dodać pole w `src/views/pages/persons/edit.php`:
  ```html
  <label>Link FindAGrave (opcjonalnie)</label>
  <input type="url" name="findagrave_url" placeholder="https://www.findagrave.com/memorial/...">
  ```
- [ ] Dodać walidację URL w `PersonController` (opcjonalne, nullable)
- [ ] Zaktualizować `PersonService::update()` / `PersonRepository::update()` o nowe pole

### 3.3 Widok osoby

- [ ] W `src/views/pages/persons/show.php` — wyświetlić link gdy `findagrave_url` ustawiony:
  ```html
  <?php if ($person->findagraveUrl): ?>
    <a href="<?= htmlspecialchars($person->findagraveUrl) ?>" target="_blank" rel="noopener">
      <?php render_icon('cross', 'solid', 'h-4 w-4') ?> FindAGrave
    </a>
  <?php endif; ?>
  ```

---

## Faza 4: BillionGraves via FamilySearch

- [ ] Brak osobnej implementacji — automatycznie pokryte przez istniejący plan `registries` (FamilySearch API).
- [ ] Zweryfikować po implementacji FamilySearch czy wyniki BillionGraves pojawiają się w wynikach.

---

## Notatki

- `search_jobs` i `registry_cache` są już w schemacie — nie duplikować migracji
- Jeśli Grobonet zmieni strukturę HTML → `_parse_results()` zwróci `[]` (pustą listę), nie błąd
- Python `robotparser` MUSI być sprawdzony przed pierwszym requestem do każdego hosta
- Rate limit 2s dla Grobonet (podwójny margines bezpieczeństwa vs standardowe 1s)
