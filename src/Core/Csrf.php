<?php
declare(strict_types=1);

namespace App\Core;

class Csrf
{
    private const TOKEN_KEY = '_csrf_token';

    public static function generate(): string
    {
        if (!isset($_SESSION[self::TOKEN_KEY])) {
            $_SESSION[self::TOKEN_KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::TOKEN_KEY];
    }

    public static function verify(string $token): bool
    {
        $stored = $_SESSION[self::TOKEN_KEY] ?? '';
        return hash_equals($stored, $token);
    }

    public static function getToken(): string
    {
        return $_SESSION[self::TOKEN_KEY] ?? self::generate();
    }

    public static function hiddenInput(): string
    {
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars(self::getToken(), ENT_QUOTES | ENT_HTML5)
        );
    }
}
