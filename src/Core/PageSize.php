<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Whitelist dozwolonych rozmiarów strony do druku/PDF.
 *
 * Single source of truth dla controllerów (TreeController::printView, PersonController::printList)
 * i layoutu (PrintLayout.php). Chroni przed CSS injection w `@page { size: ... }`.
 */
final class PageSize
{
    public const A3_LANDSCAPE = 'A3 landscape';
    public const A3_PORTRAIT  = 'A3 portrait';
    public const A4_LANDSCAPE = 'A4 landscape';
    public const A4_PORTRAIT  = 'A4 portrait';

    public const ALL = [
        self::A3_LANDSCAPE,
        self::A3_PORTRAIT,
        self::A4_LANDSCAPE,
        self::A4_PORTRAIT,
    ];

    public const DEFAULT = self::A3_LANDSCAPE;

    /** Sanityzuje wartość wejściową — fallback do `A3 landscape` przy nieprawidłowej. */
    public static function sanitize(?string $value): string
    {
        if ($value !== null && in_array($value, self::ALL, true)) {
            return $value;
        }
        return self::DEFAULT;
    }

    /** Domyślny margines dla danego rozmiaru strony. */
    public static function defaultMargin(string $pageSize): string
    {
        return ($pageSize === self::A4_PORTRAIT || $pageSize === self::A3_PORTRAIT)
            ? '15mm'
            : '10mm';
    }
}
