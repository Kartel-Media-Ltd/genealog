<?php
declare(strict_types=1);

namespace App\Models;

class Relationship
{
    public const TYPES = ['parent', 'child', 'spouse', 'sibling', 'partner'];

    public function __construct(
        public readonly string  $id,
        public readonly string  $treeId,
        public readonly string  $personAId,
        public readonly string  $personBId,
        public readonly string  $type,
        public readonly ?string $startDate,
        public readonly ?string $endDate,
        public readonly ?string $notes,
        public readonly string  $createdAt,
        // Joined fields (optional)
        public readonly ?string $relatedFirstName = null,
        public readonly ?string $relatedLastName  = null,
        public readonly ?string $relatedPhotoPath = null,
        public readonly ?string $relatedGender    = null,
        public readonly ?string $relatedBirthDate = null,
        public readonly ?string $relatedDeathDate = null,
        public readonly bool    $relatedIsLiving  = true,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:               $d['id'],
            treeId:           $d['tree_id'],
            personAId:        $d['person_a_id'],
            personBId:        $d['person_b_id'],
            type:             $d['type'],
            startDate:        $d['start_date'] ?? null,
            endDate:          $d['end_date'] ?? null,
            notes:            $d['notes'] ?? null,
            createdAt:        $d['created_at'],
            relatedFirstName: $d['related_first_name'] ?? null,
            relatedLastName:  $d['related_last_name']  ?? null,
            relatedPhotoPath: $d['related_photo_path'] ?? null,
            relatedGender:    $d['related_gender']     ?? null,
            relatedBirthDate: $d['related_birth_date'] ?? null,
            relatedDeathDate: $d['related_death_date'] ?? null,
            relatedIsLiving:  isset($d['related_is_living']) ? (bool)(int)$d['related_is_living'] : true,
        );
    }

    public function relatedFullName(): string
    {
        return trim(($this->relatedFirstName ?? '') . ' ' . ($this->relatedLastName ?? ''));
    }

    /** Human-readable type label (Polish) */
    public function typeLabel(): string
    {
        return match($this->type) {
            'parent'  => 'Rodzic',
            'child'   => 'Dziecko',
            'spouse'  => 'Małżonek/Małżonka',
            'sibling' => 'Rodzeństwo',
            'partner' => 'Partner/Partnerka',
            default   => $this->type,
        };
    }
}
