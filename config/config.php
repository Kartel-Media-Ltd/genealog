<?php
declare(strict_types=1);

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(dirname(__DIR__), '.env.local');
$dotenv->load();
$dotenv->required(['DATABASE_HOST', 'DATABASE_NAME', 'DATABASE_USER', 'DATABASE_PASSWORD'])->notEmpty();

// Środowisko
define('APP_ENV',   $_ENV['APP_ENV']   ?? 'development');
define('APP_DEBUG', (bool)($_ENV['APP_DEBUG'] ?? (APP_ENV === 'development')));
define('SITE_URL',  rtrim($_ENV['SITE_URL'] ?? 'http://localhost:8002', '/'));

// Baza danych
define('DB_HOST',    $_ENV['DATABASE_HOST']);
define('DB_PORT',    $_ENV['DATABASE_PORT'] ?? '3306');
define('DB_NAME',    $_ENV['DATABASE_NAME']);
define('DB_USER',    $_ENV['DATABASE_USER']);
define('DB_PASS',    $_ENV['DATABASE_PASSWORD']);
define('DB_CHARSET', 'utf8mb4');

// Sesja
define('SESSION_NAME',     $_ENV['SESSION_NAME']     ?? 'genealog_session');
define('SESSION_LIFETIME', (int)($_ENV['SESSION_LIFETIME'] ?? 7200));
define('SESSION_ABSOLUTE', (int)($_ENV['SESSION_ABSOLUTE'] ?? 28800)); // 8h

// Bezpieczeństwo
define('BCRYPT_COST',         12);
define('RATE_LIMIT_ATTEMPTS', 5);
define('RATE_LIMIT_WINDOW',   900); // 15 min

// Discovery — próg historyczności (lat wstecz od daty urodzenia).
// Osoby bez daty urodzenia oznaczone jako nieżyjące (is_living=0) są zawsze traktowane
// jako historyczne — brak daty = niemożność weryfikacji życia, ryzyko minimalne.
define('DISCOVERY_HISTORICAL_YEARS', (int)($_ENV['DISCOVERY_HISTORICAL_YEARS'] ?? 100));

// RODO Art. 7(1) — wersja regulaminu akceptowana przy rejestracji.
// Zmiana tej wartości = nowi userzy akceptują nową wersję. Istniejący userzy
// zostają z poprzednią wersją — rozważ mechanizm re-acceptance przy kolejnych
// zmianach regulaminu (np. prompt po logowaniu).
define('TERMS_VERSION', '2026-04-08');

// ZAD-2.8 / ZAD-3.7: dane administratora + DPO — RODO Art. 13-14.
// W produkcji uzupełnij przez env variables, nie hardkoduj.
// Wartości "[TODO]" są świadomą sygnalizacją że wdrożenie wymaga ustaleń prawnych.
define('COMPANY_NAME',    $_ENV['COMPANY_NAME']    ?? '[TODO: nazwa administratora]');
define('COMPANY_ADDRESS', $_ENV['COMPANY_ADDRESS'] ?? '[TODO: adres]');
define('COMPANY_NIP',     $_ENV['COMPANY_NIP']     ?? '[TODO: NIP]');
define('DPO_EMAIL',       $_ENV['DPO_EMAIL']       ?? '[TODO: dpo@example.com]');
define('CONTACT_EMAIL',   $_ENV['CONTACT_EMAIL']   ?? '[TODO: kontakt@example.com]');
define('SERVER_LOCATION', $_ENV['SERVER_LOCATION'] ?? '[TODO: lokalizacja serwera EU]');

// Ścieżki
define('ROOT_PATH',    dirname(__DIR__));
define('SRC_PATH',     ROOT_PATH . '/src');
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('VIEWS_PATH',   SRC_PATH . '/views');
define('TRUSTED_PROXIES', array_filter(explode(',', $_ENV['TRUSTED_PROXIES'] ?? '')));
define('UPLOAD_MAX_MB', (int)($_ENV['UPLOAD_MAX_MB'] ?? 10));

// Redis — opcjonalny (Discovery async queue + rate limiting)
// Konfiguracja przez .env.local:
//   REDIS_SOCKET=/var/run/redis/redis.sock   (Unix socket)
//   lub: REDIS_HOST=127.0.0.1  +  REDIS_PORT=6379  (TCP)
// Brak obu zmiennych = Discovery działa synchronicznie (fallback <1000 osób).
define('REDIS_SOCKET', $_ENV['REDIS_SOCKET'] ?? null);
define('REDIS_HOST',   $_ENV['REDIS_HOST']   ?? null);
define('REDIS_PORT',   $_ENV['REDIS_PORT']   ?? '6379');
