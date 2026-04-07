<?php
declare(strict_types=1);

namespace App\Core;

class DateHelper
{
    /**
     * Bezpieczne parsowanie daty w formacie Y-m-d (PHP 8.3 safe).
     *
     * Nie rzuca DateMalformedStringException dla niepoprawnych wartości
     * (`0000-00-00`, puste stringi, śmieci) — zwraca null.
     *
     * @param string|null $value Surowa wartość z DB (`Y-m-d` lub `Y-m-d H:i:s`)
     */
    public static function parseDate(?string $value): ?\DateTimeImmutable
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        $value = substr($value, 0, 10);
        $dt    = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if ($dt === false) {
            return null;
        }

        $errors = \DateTimeImmutable::getLastErrors();
        if (is_array($errors) && (($errors['error_count'] ?? 0) > 0 || ($errors['warning_count'] ?? 0) > 0)) {
            return null;
        }

        return $dt;
    }

    /**
     * Oblicza wiek osoby w pełnych latach na podstawie daty urodzenia
     * i opcjonalnej daty śmierci. Dla żyjących używa "teraz".
     *
     * @return int|null Wiek lub null gdy data urodzenia jest niepoprawna.
     */
    public static function ageInYears(?string $birthDate, ?string $deathDate = null): ?int
    {
        $birth = self::parseDate($birthDate);
        if ($birth === null) {
            return null;
        }
        $end = self::parseDate($deathDate) ?? new \DateTimeImmutable();
        return (int)$birth->diff($end)->y;
    }
}
