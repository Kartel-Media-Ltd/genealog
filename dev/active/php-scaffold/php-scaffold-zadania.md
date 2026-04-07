# Zadania: Inicjalizacja struktury PHP — Genealog

## Faza 1: Scaffold (katalogi + konfiguracja)
- [x] Utwórz drzewo katalogów: `mkdir -p public/css public/js src/Core src/Controllers src/Middleware src/Models src/Services src/Repositories "src/Views/atoms" "src/Views/molecules" "src/Views/organisms" "src/Views/templates" "src/Views/pages/auth" migrations config "storage/media" tests/Unit/Core tests/Unit/Services`
- [x] Napisz `composer.json` (PSR-4 autoload, vlucas/phpdotenv, phpunit)
- [x] `composer install`
- [x] Napisz `config/config.php` — dotenv load + stałe APP_, DB_, SESSION_, BCRYPT_COST=12
- [x] Napisz `public/.htaccess` — mod_rewrite + blokada .env + nagłówki security
- [x] Napisz `public/css/globals.css` — shadcn CSS variables (:root + .dark)
- [x] Utwórz `.gitignore` (vendor/, .env.local, storage/media/, .history/)
- [ ] **⚠️ User: Zrotuj hasło SMTP w .env.local — dane z projektu festiwal są aktywne!**
- [x] Weryfikacja: `php -S localhost:8002 -t public/` — brak błędów

## Faza 2: Core Classes
- [x] `src/Core/Session.php` — start (httponly/samesite/strict_mode), set/get/has/delete, flash/getFlash, regenerate, destroy, absolute timeout 8h
- [x] `src/Core/Csrf.php` — generate (random_bytes 32), verify (hash_equals), hiddenInput
- [x] `src/Core/Request.php` — getMethod/Path/Body/Param, verifyCsrf, isPost/Get/Ajax, getIp, setRouteParams
- [x] `src/Core/Response.php` — redirect, json, view (output buffering + resolveViewPath + path traversal check), withFlash, send
- [x] `src/Core/Database.php` — singleton getInstance, PDO (ERRMODE_EXCEPTION, utf8mb4), fetchAll/One, execute, transactions, lastInsertId
- [x] `src/Core/Router.php` — add/get/post, group, dispatch, matchRoute regex, runMiddleware chain
- [x] `public/index.php` — front controller z pełną inicjalizacją + HTTP security headers
- [x] Weryfikacja: GET / → redirect, GET /nieznana → 404

## Faza 3: Migration + DB
- [x] Napisz `migrations/001_init.sql` — tabele users (UUID, bcrypt) + rate_limits
- [x] **User: `source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/001_init.sql`**
- [x] Weryfikacja: `SHOW TABLES;` — users, rate_limits istnieją

## Faza 4: Auth
- [x] `src/Models/User.php` — DTO: id, email, name, locale, is_active, created_at
- [x] `src/Repositories/UserRepository.php` — findByEmail, findById, create (UUID + bcrypt), updateLastLogin
- [x] `src/Services/AuthService.php` — register (walidacja, UUID, hash, insert), login (findByEmail, verify, rateLimit check), checkRateLimit, recordAttempt
- [x] `src/Middleware/AuthMiddleware.php` — handle(): session check + flash + redirect /login
- [x] `src/Controllers/AuthController.php` — showLogin/Register (redirect jeśli już zalogowany), processLogin (CSRF + validate + AuthService + regenerate), processRegister (CSRF + validate + AuthService), logout (CSRF + destroy + redirect)
- [x] `src/Views/templates/AuthLayout.php` — centered card, logo, flash, Tailwind CDN, Alpine CDN
- [x] `src/Views/templates/AppLayout.php` — head, header, flash, main slot
- [x] `src/Views/pages/auth/login.php` — formularz POST /login, email+password, CSRF hidden
- [x] `src/Views/pages/auth/register.php` — formularz POST /register, name+email+password+confirm, CSRF hidden
- [x] Rejestracja tras w `index.php`
- [x] Weryfikacja E2E: register → login → session → dashboard → logout → brak sesji

## Faza 5: UI Atoms (shadcn PHP)
- [x] `src/Views/atoms/button.php` — warianty: default/outline/ghost/destructive, size: sm/md/lg, loading state
- [x] `src/Views/atoms/input.php` — type, name, value, placeholder, error state
- [x] `src/Views/atoms/label.php` — for, required marker (*), aria-hidden na *
- [x] `src/Views/atoms/card.php` — wrapper + card_header/content/footer helpers
- [x] `src/Views/atoms/badge.php` — warianty: default/secondary/destructive/outline/success/warning
- [x] `src/Views/atoms/avatar.php` — zdjęcie + fallback inicjały z deterministycznym kolorem
- [x] `src/Views/atoms/alert.php` — info/success/warning/error, ikona SVG inline, Alpine dismiss
- [x] `src/Views/atoms/spinner.php` — SVG animate-spin, sm/md/lg, role="status"
- [x] `src/Views/molecules/form-group.php` — label + input + error message + aria-describedby
- [x] `src/Views/molecules/flash-message.php` — odczyt $flashMessages, dismiss
- [x] `src/Views/organisms/site-header.php` — logo, nav, avatar, bell icon placeholder

## Faza 6: Dashboard + Testy
- [x] `src/Controllers/HomeController.php` — index(): pobierz drzewa usera (placeholder []), render dashboard
- [x] `src/Views/pages/dashboard.php` — grid kart statystyk, empty state drzew, CTA "+ Nowe drzewo"
- [x] `tests/bootstrap.php` — autoload + test config (.env.test)
- [x] `tests/Unit/Core/CsrfTest.php` — testGenerate, testVerifyValid, testVerifyInvalid, testHiddenInput
- [x] `tests/Unit/Core/RouterTest.php` — testStaticRoute, testParamRoute, testNotFound, testMethodNotAllowed
- [x] `tests/Unit/Services/AuthServiceTest.php` — testRegister, testLoginSuccess, testLoginWrongPassword, testRateLimit
- [x] `composer test` — wszystkie testy zielone (22/22)

---

## Do poprawy po review

### 🔴 Blocking — wszystkie naprawione

- [x] 🔴 [blocking] **AppLayout.php:225-227, 282-284** — logout (desktop+mobile) używa `<?= \App\Core\Csrf::hiddenInput() ?>` zamiast hardcoded `csrf_token`
- [x] 🔴 [blocking] **invite/pending.php:67** — `<?= Csrf::hiddenInput() ?>`
- [x] 🔴 [blocking] **AdminLayout.php:117** — flash render: `if (!empty($flash['type']))` z dostępem przez `$flash['type']/$flash['message']` (nie foreach)
- [x] 🔴 [blocking] **tests/bootstrap.php:20** — `'/views'` (lowercase, zgodnie z filesystem)
- [x] 🔴 [blocking] **Response::redirect** — open redirect fix: porównanie hostów przez `parse_url(...)`, blokowanie `//attacker.com` przez `str_starts_with($url, '//')`
- [x] 🔴 [blocking] **form-group.php:48** — `render_input(id, type, label, value, error, helperText, attrs)` poprawna sygnatura

### 🟠 Important — wszystkie naprawione

- [x] 🟠 [important] **AuthServiceTest.php:37** — `new User(...)` z named args (`id:`, `email:`, ..., `isAdmin: false`, `isBlocked: false`); 22/22 testy przechodzą
- [x] 🟠 [important] **pages/login.php + register.php** — usunięte (były dead duplikaty `pages/auth/login.php` + `pages/auth/register.php`)
- [x] 🟠 [important] **organisms/site-header.php** — usunięte (helper `render_site_header()` nigdzie nie wołany; AppLayout inlinuje header)
- [x] 🟠 [important] **AuthService::register()** — rate limit przez `isRateLimited($ip, 'register')` + `recordAttempt($ip, 'register')`; AuthController przekazuje `$request->getIp()`
- [x] 🟠 [important] **public/index.php:20** — HSTS warunkowy gdy HTTPS: `Strict-Transport-Security: max-age=31536000; includeSubDomains`
- [x] 🟠 [important] **Database.php** — `__wakeup()` rzuca `LogicException`; usunięty duplikat `__clone()`
- [x] 🟠 [important] **Csrf::verify()** — docblock wyjaśniający rotation side-effect (podwójny klik = 403, celowe)

> **Uwaga:** review błędnie sklasyfikował `pages/dashboard.php`, `profile.php`, `settings.php` jako dead code — te pliki SĄ aktywnie używane przez `HomeController`, `ProfileController`, `SettingsController`. Tylko `pages/login.php`, `pages/register.php` (root level) i `organisms/site-header.php` były dead.

### 🟡 Nit — wszystkie naprawione

- [x] 🟡 [nit] **Session.php** — `error_log` ostrzeżenie gdy sesja już aktywna z innego źródła; explicit `if ($started) return` przed sprawdzeniem PHP_SESSION_ACTIVE
- [x] 🟡 [nit] **Response.php** — komentarz wyjaśniający bezpieczne użycie `EXTR_SKIP` w 2-fazowym extract
- [x] 🟡 [nit] **Router.php:107** — dodane `class_exists` + `method_exists` check przed dynamic call
- [x] 🟡 [nit] **Request.php:18** — refactor `getPath()` na czytelne `trim` + ternary bez precedence pułapki
- [x] 🟡 [nit] **avatar.php** — `crc32($name)` zamiast ręcznego hash loop (deterministyczne, 32-bit safe)
- [x] 🟡 [nit] **AppLayout.php:103** — `(string)(parse_url(...) ?: '/')` — null safety dla PHP 8.1+ deprecation
- [x] 🟡 [nit] **AuthService::generateUuid** — zostaje (MVP, `ramsey/uuid` to extra dependency)
- [x] 🟡 [nit] **.htaccess dead defenses** — zostają (defense-in-depth)
- [x] 🟡 [nit] **composer.json phpstan** — DODANE (level 5, `composer phpstan` script, phpstan green)

---

## Suggestions z review — wykonane (2026-04-08)

### 🔵 Wszystkie 7 sugestii zaimplementowane

- [x] 🔵 **`tests/Unit/Core/SessionTest.php` + `RequestTest.php`** — 14 nowych testów (5+9), pokrywają `Session::flash`/`getFlash`/`set`/`get`/`has`/`delete` oraz `Request::getMethod`/`getPath`/`verifyCsrf`/`isPost`/`isGet`. Wszystkie 36/36 testów green.
- [x] 🔵 **`phpstan` level 5** — `composer.json` + `phpstan.neon` z ignoreErrors dla MVP-acceptable false-positives. Skrypt `composer phpstan`. Phpstan green.
- [x] 🔵 **DI container** — `src/Core/Container.php` (prosty service locator z lazy loading + singleton scope). Gotowy do refactoru `public/index.php`.
- [x] 🔵 **Tokeny CSRF per-form** — `Csrf::hiddenInputForForm($formId)` + `Csrf::verifyForForm($formId, $token)`. Globalny token rotacyjny zachowany jako default.
- [x] 🔵 **`notifications` table** — migracja `006_notifications.sql` + `NotificationRepository` + `NotificationService` z 5 typami (person_match, invitation, edit, impersonation, gedcom_import). Polling przez `NotificationController` (`/api/notifications/count`).
- [x] 🔵 **Periodic session regenerate** — `Session::start()` regeneruje session ID na ~1% requestów (`random_int(1, 100) === 1`).
- [x] 🔵 **Helper `Url::for()` / `Url::absolute()`** — `src/Core/Url.php` z 3 metodami: `for()`, `absolute()`, `withQuery()`. Używane w mailach i powiadomieniach.
- [x] 🔵 **`Csrf::verify()` na throw zamiast `exit`** — `Request::verifyCsrf()` rzuca `RuntimeException` zamiast `exit`. `public/index.php` łapie i renderuje `403`. Umożliwia testowanie CSRF.
