<?php
declare(strict_types=1);

namespace App\Core;

class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_samesite', 'Strict');

        session_name(SESSION_NAME);
        session_start([
            'cookie_lifetime' => 0,
            'cookie_secure'   => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        ]);

        self::$started = true;

        // Absolute session timeout (8h)
        if (isset($_SESSION['_created_at']) && time() - (int)$_SESSION['_created_at'] > SESSION_ABSOLUTE) {
            self::destroy();
            header('Location: /login');
            exit;
        }
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = time();
        }
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    public static function getFlash(): array
    {
        $flash = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return $flash;
    }

    public static function regenerate(bool $deleteOld = true): void
    {
        session_regenerate_id($deleteOld);
        $_SESSION['_created_at'] = time();
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
        self::$started = false;
    }
}
