# Zadania: Inicjalizacja struktury PHP — Genealog

## Faza 1: Scaffold (katalogi + konfiguracja)
- [ ] Utwórz drzewo katalogów: `mkdir -p public/css public/js src/Core src/Controllers src/Middleware src/Models src/Services src/Repositories "src/Views/atoms" "src/Views/molecules" "src/Views/organisms" "src/Views/templates" "src/Views/pages/auth" migrations config "storage/media" tests/Unit/Core tests/Unit/Services`
- [ ] Napisz `composer.json` (PSR-4 autoload, vlucas/phpdotenv, phpunit)
- [ ] `composer install`
- [ ] Napisz `config/config.php` — dotenv load + stałe APP_, DB_, SESSION_, BCRYPT_COST=12
- [ ] Napisz `public/.htaccess` — mod_rewrite + blokada .env + nagłówki security
- [ ] Napisz `public/css/globals.css` — shadcn CSS variables (:root + .dark)
- [ ] Utwórz `.gitignore` (vendor/, .env.local, storage/media/, .history/)
- [ ] **⚠️ User: Zrotuj hasło SMTP w .env.local — dane z projektu festiwal są aktywne!**
- [ ] Weryfikacja: `php -S localhost:8002 -t public/` — brak błędów

## Faza 2: Core Classes
- [ ] `src/Core/Session.php` — start (httponly/samesite/strict_mode), set/get/has/delete, flash/getFlash, regenerate, destroy, absolute timeout 8h
- [ ] `src/Core/Csrf.php` — generate (random_bytes 32), verify (hash_equals), hiddenInput
- [ ] `src/Core/Request.php` — getMethod/Path/Body/Param, verifyCsrf, isPost/Get/Ajax, getIp, setRouteParams
- [ ] `src/Core/Response.php` — redirect, json, view (output buffering + resolveViewPath + path traversal check), withFlash, send
- [ ] `src/Core/Database.php` — singleton getInstance, PDO (ERRMODE_EXCEPTION, utf8mb4), fetchAll/One, execute, transactions, lastInsertId
- [ ] `src/Core/Router.php` — add/get/post, group, dispatch, matchRoute regex, runMiddleware chain
- [ ] `public/index.php` — front controller z pełną inicjalizacją + HTTP security headers
- [ ] Weryfikacja: GET / → redirect, GET /nieznana → 404

## Faza 3: Migration + DB
- [ ] Napisz `migrations/001_init.sql` — tabele users (UUID, bcrypt) + rate_limits
- [ ] **User: `source .env.local && docker exec -i mariadb_docker mariadb -u $DATABASE_USER -p$DATABASE_PASSWORD $DATABASE_NAME < migrations/001_init.sql`**
- [ ] Weryfikacja: `SHOW TABLES;` — users, rate_limits istnieją

## Faza 4: Auth
- [ ] `src/Models/User.php` — DTO: id, email, name, locale, is_active, created_at
- [ ] `src/Repositories/UserRepository.php` — findByEmail, findById, create (UUID + bcrypt), updateLastLogin
- [ ] `src/Services/AuthService.php` — register (walidacja, UUID, hash, insert), login (findByEmail, verify, rateLimit check), checkRateLimit, recordAttempt
- [ ] `src/Middleware/AuthMiddleware.php` — handle(): session check + flash + redirect /login
- [ ] `src/Controllers/AuthController.php` — showLogin/Register (redirect jeśli już zalogowany), processLogin (CSRF + validate + AuthService + regenerate), processRegister (CSRF + validate + AuthService), logout (CSRF + destroy + redirect)
- [ ] `src/Views/templates/AuthLayout.php` — centered card, logo, flash, Tailwind CDN, Alpine CDN, SRI
- [ ] `src/Views/templates/AppLayout.php` — head (CSP meta), header organism, flash, main slot, SRI na CDN
- [ ] `src/Views/pages/auth/login.php` — formularz POST /login, email+password, CSRF hidden
- [ ] `src/Views/pages/auth/register.php` — formularz POST /register, name+email+password+confirm, CSRF hidden
- [ ] Rejestracja tras w `index.php`
- [ ] Weryfikacja E2E: register → login → session → dashboard → logout → brak sesji

## Faza 5: UI Atoms (shadcn PHP)
- [ ] `src/Views/atoms/button.php` — warianty: default/outline/ghost/destructive, size: sm/md/lg, loading state
- [ ] `src/Views/atoms/input.php` — type, name, value, placeholder, error state (czerwona ramka + aria-invalid)
- [ ] `src/Views/atoms/label.php` — for, required marker (*), aria-hidden na *
- [ ] `src/Views/atoms/card.php` — wrapper + card_header/content/footer helpers
- [ ] `src/Views/atoms/badge.php` — warianty: default/secondary/destructive/outline/success/warning
- [ ] `src/Views/atoms/avatar.php` — zdjęcie + fallback inicjały z deterministycznym kolorem, onerror JS
- [ ] `src/Views/atoms/alert.php` — info/success/warning/error, ikona SVG inline, Alpine dismiss, role="alert"
- [ ] `src/Views/atoms/spinner.php` — SVG animate-spin, sm/md/lg, role="status" lub aria-hidden
- [ ] `src/Views/molecules/form-group.php` — label + input + error message + aria-describedby
- [ ] `src/Views/molecules/flash-message.php` — odczyt $flashMessages, dismiss, slide-in animacja
- [ ] `src/Views/organisms/site-header.php` — logo, nav, avatar, bell icon placeholder

## Faza 6: Dashboard + Testy
- [ ] `src/Controllers/HomeController.php` — index(): pobierz drzewa usera (placeholder []), render dashboard
- [ ] `src/Views/pages/dashboard.php` — grid kart statystyk, empty state drzew, CTA "+ Nowe drzewo"
- [ ] `tests/bootstrap.php` — autoload + test config (.env.test)
- [ ] `tests/Unit/Core/CsrfTest.php` — testGenerate, testVerifyValid, testVerifyInvalid, testHiddenInput
- [ ] `tests/Unit/Core/RouterTest.php` — testStaticRoute, testParamRoute, testNotFound, testMethodNotAllowed
- [ ] `tests/Unit/Services/AuthServiceTest.php` — testRegister, testLoginSuccess, testLoginWrongPassword, testRateLimit
- [ ] `composer test` — wszystkie testy zielone
