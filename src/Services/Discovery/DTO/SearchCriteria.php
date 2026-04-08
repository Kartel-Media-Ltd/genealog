<?php
declare(strict_types=1);

namespace App\Services\Discovery\DTO;

/**
 * Kryteria wyszukiwania osoby. Wejście od usera, normalizowane pod fingerprint.
 */
final class SearchCriteria
{
    /** @var list<string> */
    private const VALID_GENDERS = ['male', 'female', 'unknown'];

    public function __construct(
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?int    $birthYear,
        public readonly ?string $birthPlace,
        public readonly ?int    $deathYear  = null,
        public readonly ?string $deathPlace = null,
        public readonly ?string $gender     = null,  // 'male'|'female'|'unknown'|null (null = nieznana)
        public readonly ?string $maidenName = null,
    ) {}

    public static function fromArray(array $input): self
    {
        $firstName = trim((string)($input['firstName'] ?? $input['first_name'] ?? ''));
        $lastName  = trim((string)($input['lastName']  ?? $input['last_name']  ?? ''));

        $rawYear = $input['birthYear'] ?? $input['birth_year'] ?? null;
        $birthYear = null;
        if ($rawYear !== null && $rawYear !== '') {
            $y = (int)$rawYear;
            $birthYear = ($y >= 1000 && $y <= 9999) ? $y : null;
        }

        $rawDeathYear = $input['deathYear'] ?? $input['death_year'] ?? null;
        $deathYear = null;
        if ($rawDeathYear !== null && $rawDeathYear !== '') {
            $d = (int)$rawDeathYear;
            $deathYear = ($d >= 1000 && $d <= 9999) ? $d : null;
        }

        $birthPlace = trim((string)($input['birthPlace'] ?? $input['birth_place'] ?? ''));
        $deathPlace = trim((string)($input['deathPlace'] ?? $input['death_place'] ?? ''));

        $rawGender = trim((string)($input['gender'] ?? ''));
        $gender    = in_array($rawGender, self::VALID_GENDERS, true) ? $rawGender : null;

        $maidenName = trim((string)($input['maidenName'] ?? $input['maiden_name'] ?? ''));

        return new self(
            firstName:  $firstName,
            lastName:   $lastName,
            birthYear:  $birthYear,
            birthPlace: $birthPlace !== '' ? $birthPlace : null,
            deathYear:  $deathYear,
            deathPlace: $deathPlace !== '' ? $deathPlace : null,
            gender:     $gender,
            maidenName: $maidenName !== '' ? $maidenName : null,
        );
    }

    public function isSearchable(): bool
    {
        // Minimum do sensownego search: co najmniej 2 znaki imienia LUB nazwiska
        return mb_strlen($this->firstName) >= 2 || mb_strlen($this->lastName) >= 2;
    }
}
