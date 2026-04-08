# Audyt jakości kodu — Genealog (backend) — Re-audit #3

**Data:** 2026-04-08

---

## Metryki LOC

```
Controllers:   15 plików    2697 LOC    avg 180 LOC   ✅
Services:      17 plików    4056 LOC    avg 239 LOC   ⚠️  (2 god classes)
Repositories:   8 plików    1062 LOC    avg 133 LOC   ✅
Core:          13 plików        ~       avg ~150 LOC  ✅
Middleware:     2 pliki     ~100 LOC    avg  50 LOC   ✅
Views:         45 plików        ~       ~3500 LOC     ⚠️  strict_types missing
Tests:          5 plików        ~       ~500 LOC      ❌ ~9% coverage
```

### God classes (>300 LOC)
- `GedcomService` — **775 LOC** (świadomie odroczone, backend Faza 5)
- `AdminService` — 218 LOC ✅ OK
- `SuggestionService` — 221 LOC ✅ OK

### Największe controllers
- `AdminController` — ~280 LOC (acceptable, CRUD users + impersonation)
- `PersonController` — ~300 LOC (`buildPersonHierarchy` rekurencja, kandydat na refactor)
- `TreeController` — ~250 LOC
- `DiscoveryController` — ~220 LOC (kandydat na split, ale odroczone)

---

## Dependency Analysis

### Composer packages (composer.json)
Brak czytania pliku — szacunkowo z `vendor/`:
- `fisharebest/gedcom` — **nie** używane (parser domowy w `GedcomService`)
- `phpunit/phpunit` — dev
- Brak PHPStan w dependencies (ale w CI używany z detection `[ -f vendor/bin/phpstan ]`)

### Composer audit
CI workflow `.github/workflows/ci.yml:34`:
```yaml
- run: composer audit --no-dev
```
Po ZAD-2.5 (backend-2) — blokujący (brak `|| true`).

### Dependency freshness
Nie zweryfikowano — requires:
```bash
composer outdated --direct
```

---

## Code Quality Issues

### Duplikacja

#### 1. Controller instantiation
`public/index.php` — `InvitationController` tworzony 3 razy (linie ~168, 177, 218):
```php
$ctrl = new InvitationController(...); // w grupie /invite
$ctrl = new InvitationController(...); // w grupie /invitations
$ctrl = new InvitationController(...); // w grupie /trees
```

Analogicznie `DiscoveryController` 2× (linie 219, 272).

**Cost:** Każdy request tworzy wszystkie 3/2 instancje nawet gdy route trafia tylko w jedną.
**Fix:** Lazy przez closure lub Container.

#### 2. `new GedcomService(...)` wewnątrz serwisów
- `src/Services/DataExportService.php:91-92` — `$gedcomService = new GedcomService(...)`
- `src/Controllers/GedcomController.php` — przekazywane przez konstruktor (OK)

Fix: Inject przez konstruktor w `DataExportService`.

#### 3. UUID generation
Po backend Faza 4 — wszystkie `bin2hex(random_bytes(16))` zamienione na `Uuid::generate()`. Nie ma duplikacji.

### Dead code

#### 1. `src/Core/Container.php`
Istnieje klasa DI Container, ale **nie używana**. Bootstrap `public/index.php` nadal używa manualnego `new`. Albo:
- Aktywować (wymaga refactora index.php) — backend-2 Faza 3 odroczone
- Usunąć (martwy kod sygnalizuje niedokończone rzeczy)

Rekomendacja: aktywować.

#### 2. `imports: use App\...` niewykorzystane
Nie zweryfikowano automatycznie — wymaga PHPStan lub `php-cs-fixer --no-unused-imports`.

### Inconsistency

#### 1. Rate limiter użycie
`AuthService` używa fallback na własny query gdy `RateLimiter` nie wstrzyknięty (backward compat dla testów). Pozostałe kontrolery używają tylko `RateLimiter`. Powinno być spójne — zawsze DI lub zawsze fallback.

#### 2. Flash messages
- `src/Core/Response.php::withFlash` — API wrappera
- `src/views/molecules/flash-messages.php:28-54` — bezpośredni dostęp `$_SESSION['flash']`, `$_SESSION['flashes']`

Niespójność: widok omija API Session/Response. Refactor: widok powinien czytać przez `Session::get('flash')`.

#### 3. Error handling
- `AdminController` — po ZAD-1.6: split `\InvalidArgumentException|\RuntimeException` vs `\Throwable`
- `AuthController`, `TreeController`, `PersonController`, `RelationshipController`, `InvitationController` — po backend Faza 2
- **Niespójne:** niektóre controllers mają error_log, inne nie. Powinno być standaryzowane przez helper `Errors::logAndGeneric($e, 'controller_name')` w Core.

#### 4. `declare(strict_types=1)`
- Wszystkie pliki w `src/` mają ✅
- **Widoki `src/views/*.php`** — brak w większości ❌ (backend Faza 5 odroczone)

### Komentarze

Comment density:
- Core: dobrze skomentowane — `Csrf`, `Session`, `Router`
- Services: dobrze (komentarze czemu, nie co)
- Repositories: mniej komentarzy, ale SQL czytelny
- Controllers: średnio

Brak dokumentacji PHPDoc z `@throws` w większości publicznych metod.

---

## Anti-patterns

### 1. Service Locator via Constructor Injection nadużycie
`SettingsController` konstruktor ma **6 parametrów**: Request, Response, UserRepository, AccountDeletionService, DataExportService, RateLimiter.

To blisko granicy "za dużo". Kandydat na split:
- `SettingsAccountController` (delete, update profile)
- `SettingsDataController` (export, notifications, locale)

### 2. Static state — `EventDispatcher`
Wszystkie metody statyczne. Niemożliwe do unit-testowania bez global reset. Odroczone w backend-2 Faza 3.

### 3. God method: `PersonController::buildPersonHierarchy`
60+ LOC w jednej metodzie, rekurencja, brak depth cap (P9).

### 4. Magic globals
- `BCRYPT_COST` — stała globalna zdefiniowana w `config/config.php`. Używana w `ProfileController:83`, `PasswordResetService:114`, `AuthService:48`. Lepiej: `class Security { const BCRYPT_COST = 12; }` (backend Faza 5 odroczone — nit).
- `STORAGE_PATH` — globalna stała, fallback `dirname(__DIR__, 2) . '/storage'`. Używana w `MediaService`, `AccountDeletionService`, `public/media.php`.

---

## Test Coverage

### Unit tests (obecne)
```
tests/Unit/Services/
├── AuthServiceTest.php        — register, login (6 testy)
tests/Unit/Core/
├── CsrfTest.php               — generate, verify, hash_equals
├── RouterTest.php             — GET/POST/grupowanie
├── SessionTest.php            — init, set, get, destroy
├── RequestTest.php            — getParam, getRouteParam, verifyCsrf
```

**Łącznie:** ~36 testów, wszystkie green (po ZAD-1.4 — mój fix passwordów w `AuthServiceTest`).

### Brakujące testy (krytyczne)
1. **`AccountDeletionService`** — zero testów dla najbardziej destrukcyjnej operacji (K2)
2. **`PasswordResetService`** — initiate + complete flow, timing attack jitter, anti-enum
3. **`PersonService`** — create + update + delete + relationship wiring
4. **`GedcomService`** — import + export (choć 775 LOC — trudny do testowania bez DI)
5. **`InvitationService`** — accept flow (P3!)
6. **`AdminService`** — impersonation, promote, demote, block
7. **Testy integracyjne** — end-to-end dla krytycznych routes z prawdziwym DB

### Coverage estimate
```
Covered:     AuthService (partial), Core classes (4/13)
Uncovered:   13 services, 8 repositories, 15 controllers, middleware, EventDispatcher

~9% coverage szacunkowo
```

**Gap:** Brak testów powoduje że każda zmiana w `AccountDeletionService` lub `GedcomService` wymaga manualnej weryfikacji end-to-end. Regresje (jak P7 — mój błąd z session_version) wykrywane dopiero przez audyt, nie przez CI.

---

## Git History Analysis

```
583d8e6 fix(gedcom): poprawki z code review
d29fc18 feat(gedcom): import/eksport GEDCOM 5.5.1 — fazy 1-4
319052b feat(trees): CRUD drzew genealogicznych + migracja 002
0b3227e feat(php-scaffold): kompletny scaffold PHP MVC z auth, testami i UI
075a444 feat(php-scaffold): faza 3 — migration 001_init.sql (users + rate_limits)
```

Stan: 5 committów, projekt wciąż w fazie scaffolding. Backend-2 i backend-3 fixy NIE są jeszcze zacommitowane (working tree ma 30+ M i ?? files).

**Uwaga:** Working tree zawiera mix zmian z wielu sprintów. Wymaga uporządkowania przez user (`git add -p` lub commity per zadanie).

---

## Recommendations

### Immediate (do /ultra-workaholic)
Patrz `backend-zadania.md` — 21 zadań.

### Tech debt (backlog)
- Aktywacja `Container.php` (backend-2 Faza 3)
- `GedcomService` split (backend Faza 5)
- `EventDispatcher` → instance state (backend-2 Faza 3)
- Strict_types w views (backend Faza 5)
- Testy integracyjne (minimum 4 flow)

### Process improvements
- **PHPStan level 5+** w CI (obecnie conditional)
- **Code coverage** w CI (przynajmniej report, bez threshold na start)
- **Dependabot** — automatyczne PR dla composer updates
- **Pre-commit hooks** — `php -l`, `phpstan`, `php-cs-fixer`
