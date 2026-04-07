<?php
declare(strict_types=1);

namespace App\Core;

/**
 * URL helper — generuje absolutne i względne ścieżki w aplikacji.
 *
 * Zamiast hardcodować ścieżki w widokach (`/dashboard`, `/login`),
 * używaj `Url::for('/dashboard')` i `Url::absolute('/dashboard')`.
 *
 * Korzyści:
 * - Łatwa zmiana prefiksu aplikacji w przyszłości
 * - Generowanie absolutnych URL-i w mailach (powiadomienia, zaproszenia)
 * - Single source of truth dla SITE_URL
 */
class Url
{
    /**
     * Względna ścieżka (z prefiksem aplikacji jeśli kiedyś będzie potrzebny).
     *
     * @param string $path Ścieżka zaczynająca się od `/`
     */
    public static function for(string $path): string
    {
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        return $path;
    }

    /**
     * Pełny URL z hostem — używaj w mailach i powiadomieniach.
     *
     * @param string $path Ścieżka zaczynająca się od `/`
     */
    public static function absolute(string $path): string
    {
        return rtrim(SITE_URL, '/') . self::for($path);
    }

    /**
     * Wygenerowanie URL z parametrami query string.
     *
     * @param array<string, scalar|null> $params
     */
    public static function withQuery(string $path, array $params): string
    {
        $filtered = array_filter($params, fn($v) => $v !== null && $v !== '');
        if (empty($filtered)) {
            return self::for($path);
        }
        return self::for($path) . '?' . http_build_query($filtered);
    }
}
