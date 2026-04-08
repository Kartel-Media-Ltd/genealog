# Audyt jakości kodu — Genealog (backend) — Re-audit #4

**Data:** 2026-04-08

---

## Metryki

```
Src files (php):       65     (+10 vs backend-3)
  Controllers:         15     (2697 LOC)
  Services:            22     (5256 LOC, +1200 Discovery)
  Repositories:         8     (1100 LOC, +2 metody)
  Core:                13     (~2000 LOC)
  Middleware:           2
  Discovery/ subdir:   11 files, ~1200 LOC

Views:                 ~47    (+2 privacy.php, terms.php)
Migrations:            14     (+3: 012, 013, 014)
Tests:                  6     (+1: ???)
Test coverage szac:  ~9%    (0% dla nowego Discovery module — CRITICAL GAP)
```

---

## Nowy moduł Discovery — quality assessment

### Mocne strony

**+ Clean interface design:**
`MatchSourceInterface` — 3 metody (`getName`, `isAvailable`, `search`). Minimalny, łatwy do implementacji.

**+ DTO separation:**
- `SearchCriteria` — input (what to search)
- `SearchContext` — auth info (who, which tree, IP)
- `MatchResult` — output (with jsonSerialize enforcing privacy rules)
Trzy DTOs dla trzech koncepcji — zero coupling.

**+ Exception isolation per source:**
`MatchingService::findCandidates` używa `try/catch(\Throwable) + continue` — jeden błąd nie wywala całego search.

**+ Repository pattern — dedykowane metody:**
- `NotificationRepository::existsRecentForLink` — dedup z SQL w repo, nie w service
- `UserRepository::isDiscoveryOptedIn` — explicit method zamiast `findById()->optedIn`
(User sam to dodał — dobra refaktoryzacja!)

**+ Pluggable registration:**
```php
$matchRegistry = new MatchSourceRegistry();
$matchRegistry->register(new LocalTreeMatchSource($db, $fingerprintSvc));
$matchRegistry->register(new CrossTreeMatchSource($db, $fingerprintSvc));
$matchRegistry->register(new FamilySearchMatchSource(getenv('FAMILYSEARCH_CLIENT_ID') ?: null));
$matchRegistry->register(new GenetykaMatchSource(getenv('GENETEKA_LOCAL_DB') ?: null));
```
Dodanie nowej source = 1 linia.

### Słabe strony

**- Brak testów dla całego Discovery (0%)**
0 testów dla: FingerprintService (pure function!), MatchingService, MatchSourceRegistry, PersonImportService, wszystkie Sources, DTOs.

**- Hardcoded strings w MatchingService:**
```php
if ($name === 'local')      // ← magic string
elseif ($name === 'cross_tree')
default => 'external'
```
Powinno być stałych w `MatchSourceInterface::SOURCE_LOCAL` etc.

**- `error_log` wszędzie, brak structured logging:**
- `CrossTreeMatchSource:172-177` — log per każde zapytanie z wynikami (spam)
- `PersonImportService` — też error_log
- `GlobalIndexService` — analogicznie
Brak request_id, severity levels, structured fields. Debug trudny.

**- Komentarz "asynchroniczne" w synchronicznej klasie:**
`MatchingService.php:19` docbloc mówi "asynchroniczne powiadomienia" ale wywołanie jest synchroniczne (callback w EventDispatcher static).

**- Zły komentarz wskazujący nieistniejącą metodę:**
`MatchingService.php:179` — `existsRecentForPerson` nie istnieje (jest `existsRecentForLink`).

---

## Code duplication — nowe przypadki (vs backend-3)

### 1. `getenv()` pattern powtarzany

W `public/index.php:131-132`:
```php
$matchRegistry->register(new FamilySearchMatchSource(getenv('FAMILYSEARCH_CLIENT_ID') ?: null));
$matchRegistry->register(new GenetykaMatchSource(getenv('GENETEKA_LOCAL_DB') ?: null));
```
Brak walidacji path dla Geneteka (patrz P11 audyt-cyber). Pattern będzie powtarzany dla każdej nowej external source.

**Fix:** Config helper:
```php
function env_path(string $key, string $subpath): ?string {
    $path = getenv($key) ?: null;
    if ($path === null) return null;
    $real = realpath($path);
    $allowed = realpath(STORAGE_PATH . '/' . $subpath);
    return $real && str_starts_with($real, $allowed) ? $real : null;
}
```

### 2. Fingerprint generation powtarzany

`FingerprintService` istnieje, ale czy jest używany wszędzie? Sprawdźmy referencje — jeśli jakaś service nadal robi `hash('sha256', strtolower($name) . $year)` zamiast przez FingerprintService, to duplication.

### 3. Tree validation w Sources

`LocalTreeMatchSource` i `CrossTreeMatchSource` weryfikują dostęp do drzewa różnymi sposobami. Consider extracting do `AbstractMatchSource` base class.

---

## Dead code / tech debt

### Istniejące odroczone (z backend-2 Faza 3)

- `src/Core/Container.php` — nie używane
- `EventDispatcher` static → instance refactor
- `GedcomService` 775 LOC split
- `DiscoveryController` 2× w bootstrap routingu

### Nowe (z backend-4)

- `FamilySearchMatchSource` — stub, zero tests
- `GenetykaMatchSource` — stub, zero tests, brak path validation
- Komentarze wskazujące nieistniejące metody (`existsRecentForPerson`)
- `public/index.php` nadal 56+ manualnych `new X(...)`

---

## Conventions compliance

### PSR-12 + project standards

- ✅ `declare(strict_types=1)` w każdym nowym pliku Discovery
- ✅ Namespace `App\Services\Discovery\...` spójny
- ✅ Named arguments w konstruktorach (constructor promotion)
- ✅ Readonly properties
- ✅ Type hints wszędzie (brak `mixed` poza DTO)

### Niespójności

- ⚠️ `error_log` zamiast structured (patrz wyżej)
- ⚠️ Magic strings dla source names
- ⚠️ Komentarze wskazujące nieistniejące metody

---

## PHPStan / static analysis

Status: CI ma conditional PHPStan (`[ -f vendor/bin/phpstan ]`) — nie zawsze uruchamiany.

**Rekomendacja:** Make PHPStan obowiązkowy w CI (jeśli jeszcze nie):
```yaml
- run: composer require --dev phpstan/phpstan
- run: ./vendor/bin/phpstan analyse --level=5 src/
```

Level 5 jest realistyczny dla obecnego kodu (nie blokuje per null).

---

## Test coverage breakdown

### Istniejące testy (6 plików)

```
tests/Unit/Core/CsrfTest.php           ✅
tests/Unit/Core/RequestTest.php        ✅
tests/Unit/Core/RouterTest.php         ✅
tests/Unit/Core/SessionTest.php        ✅
tests/Unit/Services/AuthServiceTest.php ✅ (36 tests total, passing)
tests/Unit/??? (new one)                ✅
```

### Brakujące testy — critical path

**CRITICAL (bez testów = ryzyko regresji):**
- `AccountDeletionService` — K2 fix nigdy nie przetestowany
- `EmailService::sanitizeHeader` — K1 fix wymaga testu jednostkowego
- `AuthController::processRegister` — K3 consent validation
- `FingerprintService` — zmiana = cały global_person_index staje się nieważny
- `InvitationService::accept` — P3 email check fix

**IMPORTANT:**
- `MatchingService::findAndNotifyMatches`
- `PersonImportService::importFromMatch`
- `DataExportService::generateExport`
- `PasswordResetService` — initiate + complete
- `GedcomService::import` — basic smoke test

**NICE-TO-HAVE:**
- Wszystkie Controllers (ale trudne bez DI container)

### Rekomendacja

**Priorytet #1 przed deploy:**
1. `tests/Unit/Services/EmailServiceTest.php` — test sanitizeHeader z \r\n\0 payloads (5 testów, ~30 min)
2. `tests/Unit/Services/Discovery/FingerprintServiceTest.php` — regression pins (3 testy, ~20 min)
3. `tests/Unit/Services/AccountDeletionServiceTest.php` — sole-owned vs shared scenarios (5 testów z DB mock, ~1h)

---

## Git history quality

```bash
583d8e6 fix(gedcom): poprawki z code review
d29fc18 feat(gedcom): import/eksport GEDCOM 5.5.1 — fazy 1-4
319052b feat(trees): CRUD drzew genealogicznych + migracja 002
0b3227e feat(php-scaffold): kompletny scaffold PHP MVC z auth, testami i UI
075a444 feat(php-scaffold): faza 3 — migration 001_init.sql (users + rate_limits)
```

Status od backend-2: **0 commitów**. Working tree ma wszystkie zmiany (backend-2 + backend-3 + Discovery + inne) niezacommitowane. **Pilnie uporządkować commit history** przed większym zmianami.

---

## Podsumowanie jakości

| Kategoria | Stan | Trend |
|-----------|------|-------|
| **Architektura** | Dobra | ↑ (pluggable Discovery) |
| **Konwencje** | Bardzo dobre | = |
| **Test coverage** | Słaby (~9%) | ↓ (nowe moduły bez testów) |
| **Code duplication** | Niskie | = |
| **Dead code** | Średnie (Container, DiscoveryController 2×) | = |
| **Dokumentacja** | Średnia | = (błędne komentarze) |
| **Logging** | Słabe (error_log wszędzie) | = |
| **CI/CD** | Dobre (composer audit blokujący) | = |
| **Ogólne** | ⚠️ PASS WITH CONDITIONS | = |

### Kluczowe rekomendacje

1. **Testy jednostkowe** — minimum 3 pliki (EmailService, FingerprintService, AccountDeletionService) przed deploy
2. **Stałe dla source names** — wyeliminuj magic strings
3. **Structured logging** — Monolog lub własny wrapper
4. **Commit history** — uporządkuj working tree
5. **PHPStan obowiązkowy w CI** — level 5+
