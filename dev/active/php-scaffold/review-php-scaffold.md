# Code Review — PHP Scaffold (fundament)

**Data:** 2026-04-07
**Branch:** feature/php-scaffold

## Podsumowanie

Scaffold ma **solidną strukturę MVC** (Router/Request/Response/Database/Session/Csrf), poprawne PSR-12, `declare(strict_types=1)`, ścieżki bezpieczeństwa (path traversal, CSRF, prepared statements, bcrypt cost 12, security headers). Niestety w warstwie widoków występuje **kilka regresji blokujących** — przede wszystkim niespójna nazwa pola CSRF (`csrf_token` vs `_csrf_token`) w aktywnych templates oraz mismatch w `AdminLayout` przy odczycie flash messages. Dodatkowo cała aplikacja używa katalogu `src/views` (lowercase), a `tests/bootstrap.php` ma `SRC_PATH . '/Views'` (uppercase) — działa wyłącznie dzięki case-insensitive APFS na macOS, na Linuksie testy/aplikacja będą się sypać. Pre-existing fail w `AuthServiceTest::testRegisterSuccess` jest faktyczny i potwierdzony.

---

## Problemy

### 🔴 Blocking

- **`src/views/templates/AppLayout.php:225-227, 282-284`** — Logout form (desktop + mobile) używa `name="csrf_token"` i odczytu `$_SESSION['_csrf_token']`. `Request::verifyCsrf()` szuka `_csrf_token` → otrzymuje pusty string → 403 na każde wylogowanie. **Fix:** `<?= Csrf::hiddenInput() ?>`.

- **`src/views/pages/invite/pending.php:67`** — Form akceptu zaproszenia używa `name="csrf_token"` → akcja `POST /invite/{token}/accept` → 403. **Fix:** `<?= Csrf::hiddenInput() ?>`.

- **`src/views/templates/AdminLayout.php:117`** — `foreach ($flash as $type => $msg)` iteruje po tablicy `['type' => '…', 'message' => '…']` zwracanej z `Session::getFlash()`. Renderuje 2 alerty: jeden z `$type='type'/$msg='error'`, drugi z `$type='message'/$msg='Twoje konto…'`. **Każdy flash w panelu admina jest renderowany dwukrotnie z mangled content.** **Fix:** `if (!empty($flash['type'])) render_alert($flash['message'], $flash['type'])`.

- **`config/config.php:37` vs `tests/bootstrap.php:20`** — niespójna ścieżka VIEWS_PATH: produkcja `SRC_PATH . '/views'` (lowercase), bootstrap `SRC_PATH . '/Views'` (uppercase). Filesystem ma tylko `src/views`. macOS/APFS ratuje, ale **na Linuksie CI wybuchnie** z `RuntimeException: View not found`. **Fix:** ujednolicić bootstrap na `'/views'`.

- **`src/Core/Response.php:13`** — Open redirect: `if (!str_starts_with($url, '/') && !str_starts_with($url, SITE_URL))`. `SITE_URL = 'http://localhost:8002'` — URL `http://localhost:8002.attacker.com/login` zaczyna się od `SITE_URL` → przepuszczany. **Fix:** porównać z `SITE_URL . '/'` lub `parse_url($url, PHP_URL_HOST)`.

- **`src/views/molecules/form-group.php:48`** — `render_input($attrs, $hasError)` jest składniowo źle. Sygnatura `render_input(string $id, string $type, string $label, string $value, string $error, string $helperText, array $attrs)`. Wywołanie z tablicą jako pierwszym argumentem rzuci `TypeError`. Aktualnie dead code, ale bug-trap przy pierwszym użyciu.

### 🟠 Important

- **`tests/Unit/Services/AuthServiceTest.php:37`** — Pre-existing fail: `new User('uuid-1', 'jan@test.pl', 'Jan', 'pl', true, true, '2026-01-01')` przekazuje 7 argumentów, ale `User::__construct` po wprowadzeniu admin/blocked ma 9 (`id, email, name, locale, isActive, emailNotifications, isAdmin, isBlocked, createdAt`). 7. argument `'2026-01-01'` ląduje jako `isAdmin: bool` → `TypeError`. **Fix:** named args lub dodanie brakujących pól.

- **`src/views/pages/login.php` + `register.php`** — Dead pages (nieroutowane), ale `require __DIR__ . '/../templates/auth-layout.php'` — plik to `AuthLayout.php` (PascalCase). Linux fail. **Fix:** usunąć dead code (używane są `pages/auth/login.php` i `register.php`).

- **`src/views/pages/dashboard.php`, `profile.php`, `settings.php` (root level)** — Kolejne dead pages, duplikaty zastąpione przez aktywne wersje. ~1500 linii martwego kodu z bugami CSRF. **Fix:** sprzątnąć.

- **`migrations/001_init.sql:23-31`** — `rate_limits` brak `UNIQUE KEY (ip, endpoint, window_start)` — bez tego `INSERT … ON DUPLICATE KEY UPDATE` w `recordAttempt` nie konfliktuje. Naprawione w `003_fixes.sql` (zostawić jak jest, plan zakazuje modyfikacji 001 po deploy).

- **`src/Services/AuthService.php`** — Brak rate limitu na **rejestrację**. Tylko `login()` chroniony. Spam rejestracji bez bariery. **Fix:** `recordAttempt(..., 'register')` + `isRateLimited(..., 'register')`.

- **`public/index.php:20`** — CSP z `'unsafe-inline' 'unsafe-eval'` praktycznie kasuje XSS-protection. Wymagane przez Tailwind CDN/Alpine. Akceptowalne dla MVP. Brak HSTS — dodać warunkowo gdy HTTPS.

- **`src/views/organisms/site-header.php`** — Dead code (222 linie, helper `render_site_header()` nigdzie nie wołany). AppLayout inlinuje header. **Fix:** usunąć lub zrefaktoryzować AppLayout aby go używał (atomic design).

- **`src/Core/Database.php`** — Singleton bez `__wakeup()` — można serializować i odtworzyć drugą instancję. Drobnostka.

- **`src/Core/Csrf.php:18-27`** — `verify()` rotuje token per-request. Szybki podwójny klik na "Logout" → drugi już ma stary token → 403. Drobny side-effect.

### 🟡 Nit

- **`src/Core/Session.php:11-15`** — `if ($started)` early return nie aplikuje ini-shotów (httponly/samesite) jeśli sesja już aktywna z innego źródła
- **`src/Core/Response.php:34, 40`** — Dwukrotny `extract()` — działa przez `EXTR_SKIP`, ale pułapka dla edge cases
- **`src/Core/Router.php:107`** — `(new $class())->$method($request)` bez `class_exists` check
- **`src/Core/Request.php:18`** — `'/' . trim($path, '/') ?: '/'` precedencja `?:` myląca
- **`src/Services/AuthService.php:78-84`** — Własny `generateUuid()`; `ramsey/uuid` byłby standardem
- **`src/views/atoms/avatar.php:104-107`** — Hash hex avatar bg color działa, ale `intval(crc32($name))` byłby czystszy
- **`src/views/templates/AppLayout.php:103`** — `parse_url(...)` może zwrócić `null` → deprecated warning w PHP 8.1+
- **`public/.htaccess:5-13`** — `FilesMatch` na `.env`, `composer.json` — żaden z tych plików nie jest w `public/` (dead defenses)
- **`composer.json`** — Brak `phpstan` / `psalm` w dev-deps

### 🔵 Suggestions

- Dodać `tests/Unit/Core/SessionTest.php` + `RequestTest.php` — żadne ze scaffold-tests nie pokrywa `Session::flash` ani `Request::verifyCsrf` (źródło 1/3 dzisiejszych bugów)
- `phpstan` level 6 — wszystkie 5 regresji CSRF byłyby wyłapane statycznie
- DI container w `src/Core/Container.php` — `public/index.php` rozrasta się liniowo
- Tokeny CSRF per-form (różne form-id) — chroni przed token leakage
- `notifications` table — wymóg z CLAUDE.md
- Periodic session regenerate (`if rand(1,100) === 1`) zamiast tylko przy login
- Helper `route()` / `url()` zamiast hard-coded ścieżek

---

## Co działa dobrze

- Wszędzie `declare(strict_types=1)`, namespacing PSR-4, czysta MVC
- Cryptographically secure CSRF: `random_bytes(32)`, `hash_equals`, rotacja po sukcesie
- PDO bez konkatenacji, `ATTR_EMULATE_PREPARES => false`
- Bcrypt cost 12, `BCRYPT_COST=4` w testach (szybkie testy)
- `Response::resolveViewPath` z `realpath` + sprawdzeniem prefiksu (path traversal)
- Session security: `httponly`, `samesite=Strict`, `cookie_secure` przy HTTPS, `use_strict_mode=1`, regeneracja po login
- HTTP security headers w front controllerze + `.htaccess` defense-in-depth
- Rate limiting pre-password-verify (timing attack mitigation)
- Routing: `{param}` regex, group prefix + middleware merge
- Atomic design: 10 atoms + molekuły + organism + templates
- WCAG: `aria-describedby`, `aria-invalid`, `role="alert"`, focus rings
- `AdminMiddleware` blokuje impersonującego z `/admin`
- Migration 003 fixes — aktywna konserwacja błędów

---

## Statystyki

| Kategoria | Liczba |
|-----------|--------|
| Plików reviewed | ~35 |
| 🔴 Blocking | 6 |
| 🟠 Important | 9 |
| 🟡 Nit | 9 |
| 🔵 Suggestions | 8 |

**Najpilniejsze (1 commit, ~30 min):**
1. `name="csrf_token"` → `Csrf::hiddenInput()` w 5 miejscach (AppLayout ×2, invite/pending, dead login/register)
2. `AdminLayout.php:117` flash render fix
3. `tests/bootstrap.php` → `'/views'` lowercase
4. `Response::redirect` open redirect fix
5. `AuthServiceTest::testRegisterSuccess` named args
6. Sprzątnięcie dead code (~1500 linii)
