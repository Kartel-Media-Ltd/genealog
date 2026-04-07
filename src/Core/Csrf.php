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

    /**
     * Weryfikuje token CSRF i — przy sukcesie — rotuje go (nowy token na następny request).
     *
     * Side-effect: szybki podwójny submit tego samego formularza po sukcesie pierwszego
     * skutkuje 403 dla drugiego (bo token został już zrotowany). To celowe — chroni przed
     * podwójnym wykonaniem akcji (np. podwójne kliknięcie "Wyloguj").
     */
    public static function verify(string $token): bool
    {
        $stored = $_SESSION[self::TOKEN_KEY] ?? '';
        $valid  = hash_equals($stored, $token) && $stored !== '';
        if ($valid) {
            unset($_SESSION[self::TOKEN_KEY]);
            self::generate();
        }
        return $valid;
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
