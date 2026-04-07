<?php
declare(strict_types=1);

namespace App\Models;

class Person
{
    public const GENDERS = ['male', 'female', 'unknown'];
    public const VISIBILITIES = ['private', 'public', 'anonymous'];

    public function __construct(
        public readonly string  $id,
        public readonly string  $treeId,
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?string $maidenName,
        public readonly ?string $birthDate,
        public readonly ?string $birthPlace,
        public readonly ?string $deathDate,
        public readonly ?string $deathPlace,
        public readonly string  $gender,
        public readonly bool    $isLiving,
        public readonly string  $visibility,
        public readonly ?string $notes,
        public readonly ?string $photoPath,
        public readonly ?string $gedcomXref,
        public readonly string  $createdBy,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:          $d['id'],
            treeId:      $d['tree_id'],
            firstName:   $d['first_name'],
            lastName:    $d['last_name'],
            maidenName:  $d['maiden_name'] ?? null,
            birthDate:   $d['birth_date'] ?? null,
            birthPlace:  $d['birth_place'] ?? null,
            deathDate:   $d['death_date'] ?? null,
            deathPlace:  $d['death_place'] ?? null,
            gender:      $d['gender'] ?? 'unknown',
            isLiving:    (bool)($d['is_living'] ?? true),
            visibility:  $d['visibility'] ?? 'private',
            notes:       $d['notes'] ?? null,
            photoPath:   $d['photo_path'] ?? null,
            gedcomXref:  $d['gedcom_xref'] ?? null,
            createdBy:   $d['created_by'],
            createdAt:   $d['created_at'],
            updatedAt:   $d['updated_at'],
        );
    }

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function birthYear(): ?int
    {
        if ($this->birthDate === null) {
            return null;
        }
        $year = (int)substr($this->birthDate, 0, 4);
        return $year > 0 ? $year : null;
    }
}
