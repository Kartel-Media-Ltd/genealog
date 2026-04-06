# Plan: Inicjalizacja struktury projektu PHP — Genealog

## Klasyfikacja
- **Typ:** STANDARD
- **Data:** 2026-04-07
- **Branch:** feature/php-scaffold

## Stack
PHP 8.2+, PDO, własny MVC Router (bez frameworka), MariaDB (Docker: mariadb_docker), vlucas/phpdotenv, Apache + .htaccess, Tailwind CSS v4 CDN, Alpine.js CDN, Composer PSR-4

## Struktura katalogów

```
genealog/
├── public/
│   ├── index.php              # Front controller
│   ├── media.php              # Serwowanie plików z auth check
│   ├── .htaccess              # mod_rewrite + security headers + blokada .env
│   ├── css/globals.css        # shadcn CSS variables
│   └── js/app.js
├── src/
│   ├── Core/
│   │   ├── Router.php         # add/get/post, group, dispatch, matchRoute (regex params)
│   │   ├── Request.php        # getMethod/Path/Body/Param, verifyCsrf, getIp
│   │   ├── Response.php       # redirect, json, view (output buffering), withFlash
│   │   ├── Database.php       # Singleton PDO, fetchAll/One, execute, transactions
│   │   ├── Session.php        # start (httponly/samesite), flash, regenerate, destroy
│   │   └── Csrf.php           # bin2hex(random_bytes(32)), hash_equals, hiddenInput
│   ├── Controllers/
│   │   ├── AuthController.php # showLogin/Register, processLogin/Register, logout
│   │   └── HomeController.php # index (dashboard)
│   ├── Middleware/
│   │   └── AuthMiddleware.php # handle() — sprawdza session['user_id']
│   ├── Models/
│   │   └── User.php           # DTO encji użytkownika
│   ├── Services/
│   │   └── AuthService.php    # register (bcrypt+UUID), login (verify+rateLimit)
│   ├── Repositories/
│   │   └── UserRepository.php # findByEmail, findById, create
│   └── Views/
│       ├── atoms/             # button, input, label, card, badge, avatar, alert, spinner
│       ├── molecules/         # form-group, flash-message
│       ├── organisms/         # site-header
│       ├── templates/         # AppLayout, AuthLayout
│       └── pages/             # auth/login, auth/register, dashboard
├── migrations/
│   └── 001_init.sql           # users + rate_limits
├── config/config.php          # dotenv load + stałe APP_, DB_, SESSION_
├── storage/media/             # WebP uploads (poza public/)
├── tests/                     # PHPUnit
├── composer.json
└── .gitignore
```

## Kluczowe klasy Core — sygnatury metod

### Router.php
- `add(string $method, string $path, string|callable $handler): self`
- `get/post(string $path, handler): self`
- `group(string $prefix, array $middleware, callable $callback): void`
- `dispatch(Request $request): void`
- `matchRoute(string $method, string $path): array|null` — regex params

### Request.php
- `getMethod/Path/Body(): string|array`
- `getParam(string $key, mixed $default = null): mixed`
- `getRouteParam(string $key): string|null`
- `verifyCsrf(): void` — rzuca SecurityException
- `isPost/Get/Json/Ajax(): bool`
- `getIp(): string`

### Response.php
- `redirect(string $url, int $code = 302): never`
- `json(mixed $data, int $code = 200): never`
- `view(string $view, array $data = [], ?string $layout = 'templates/AppLayout'): never`
- `withFlash(string $type, string $message): static`

### Database.php (Singleton PDO)
- `getInstance(): static`
- `fetchAll/fetchOne(string $sql, array $params): array|null`
- `execute(string $sql, array $params): int`
- `beginTransaction/commit/rollback(): void`

### Session.php
- `start(): void` — httponly, samesite=Strict, use_strict_mode
- `set/get/has/delete(string $key): void|mixed|bool`
- `flash/getFlash(): void|array`
- `regenerate(bool $deleteOld = true): void` — OBOWIĄZKOWE po logowaniu
- `destroy(): void`

### Csrf.php
- `generate(): string` — bin2hex(random_bytes(32)), zapis w sesji
- `verify(string $token): bool` — hash_equals (timing-safe)
- `hiddenInput(): string` — gotowy `<input type="hidden">`

## Migration SQL (001_init.sql)
```sql
CREATE TABLE users (
  id            CHAR(36)     NOT NULL,          -- UUID v4
  email         VARCHAR(255) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,           -- bcrypt cost=12
  name          VARCHAR(100) NOT NULL,
  locale        VARCHAR(10)  NOT NULL DEFAULT 'pl',
  is_active     TINYINT(1)   NOT NULL DEFAULT 1,
  email_notifications TINYINT(1) NOT NULL DEFAULT 1,
  created_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE rate_limits (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip           VARCHAR(45)  NOT NULL,
  endpoint     VARCHAR(100) NOT NULL,
  attempts     TINYINT      NOT NULL DEFAULT 1,
  window_start TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rate_ip_endpoint (ip, endpoint, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## public/index.php — Front Controller
1. `require vendor/autoload.php`
2. `require config/config.php` (dotenv + stałe)
3. Error handling (APP_DEBUG)
4. `Session::start()`
5. `new Request()`, `new Response()`
6. Inicjalizacja zależności (Database, Repositories, Services, Middleware)
7. Rejestracja tras
8. `$router->dispatch($request)` + try/catch (NotFoundException → 404, Throwable → 500)

## public/.htaccess
- `Options -Indexes`
- Blokada `<FilesMatch "^\.env">` — Require all denied
- Blokada `.git`, `composer.json/lock`, `*.md`
- `RewriteRule ^ index.php [QSA,L]`
- Nagłówki: X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy

## Architektoniczne decyzje
- **Response::view() z output buffering** — `ob_start()` → `ob_get_clean()` → wstrzyknięcie $content do layoutu
- **Database Singleton** — jedno PDO connection per request; docelowo DI container (PHP-DI)
- **UUID po stronie PHP** — `sprintf('%s-%s...', bin2hex(random_bytes(4)), ...)` lub `ramsey/uuid`
- **Router group() z middleware array** — stackowalny, kompatybilny z PSR-15 na przyszłość
- **ON DELETE RESTRICT** wszędzie — dane genealogiczne nie kasują się w kaskadzie

## Poprawki po audycie bezpieczeństwa
- [K2] Nagłówki HTTP bezpieczeństwa w `index.php` (CSP, X-Frame-Options)
- [P1] SRI dla CDN (integrity + crossorigin)
- [P2] SESSION_SECRET wygenerować: `php -r "echo bin2hex(random_bytes(32));"`
- [P4] Absolute session timeout w Session::start() (max 8h)
