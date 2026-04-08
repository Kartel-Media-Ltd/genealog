<?php
declare(strict_types=1);

namespace App\Models;

class Tree
{
    public function __construct(
        public readonly string  $id,
        public readonly string  $ownerId,
        public readonly string  $name,
        public readonly ?string $description,
        public readonly bool    $isPublic,
        public readonly bool    $isIndexedGlobally,
        public readonly ?string $discoveryConsentAt,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
        // Agregaty dołączane przez JOIN (opcjonalne)
        public readonly int     $personsCount = 0,
        public readonly ?string $ownerName    = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:                 $data['id'],
            ownerId:            $data['owner_id'],
            name:               $data['name'],
            description:        $data['description']   ?? null,
            isPublic:           (bool)($data['is_public'] ?? false),
            isIndexedGlobally:  (bool)($data['is_indexed_globally'] ?? false),
            discoveryConsentAt: $data['discovery_consent_at'] ?? null,
            createdAt:          $data['created_at']    ?? '',
            updatedAt:          $data['updated_at']    ?? '',
            personsCount:       (int)($data['persons_count'] ?? 0),
            ownerName:          $data['owner_name']    ?? null,
        );
    }
}
