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
