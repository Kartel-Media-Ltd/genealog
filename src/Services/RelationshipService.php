<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Relationship;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;

class RelationshipService
{
    public function __construct(
        private readonly RelationshipRepository $relRepo,
        private readonly PersonRepository       $personRepo,
    ) {}

    public function create(
        string  $treeId,
        string  $personAId,
        string  $personBId,
        string  $type,
        ?string $startDate = null,
        ?string $endDate   = null,
        ?string $notes     = null,
    ): Relationship {
        // Validate both persons belong to this tree
        if ($this->personRepo->findById($personAId, $treeId) === null) {
            throw new \InvalidArgumentException('Pierwsza osoba nie istnieje w tym drzewie.');
        }
        if ($this->personRepo->findById($personBId, $treeId) === null) {
            throw new \InvalidArgumentException('Druga osoba nie istnieje w tym drzewie.');
        }

        if (!in_array($type, Relationship::TYPES, true)) {
            throw new \InvalidArgumentException('Nieprawidłowy typ relacji.');
        }

        if ($personAId === $personBId) {
            throw new \InvalidArgumentException('Osoba nie może być w relacji z samą sobą.');
        }

        // Check for duplicate
        if ($this->relRepo->exists($personAId, $personBId, $type, $treeId)) {
            throw new \InvalidArgumentException('Ta relacja już istnieje.');
        }

        $id = $this->generateUuid();
        $this->relRepo->create($id, $treeId, $personAId, $personBId, $type, $startDate, $endDate, $notes);

        // Insert the inverse relationship
        $inverseType = $this->inverseType($type);
        if ($inverseType !== null && !$this->relRepo->exists($personBId, $personAId, $inverseType, $treeId)) {
            $inverseId = $this->generateUuid();
            $this->relRepo->create($inverseId, $treeId, $personBId, $personAId, $inverseType, $startDate, $endDate, $notes);
        }

        $rel = $this->relRepo->findById($id, $treeId);
        if ($rel === null) {
            throw new \RuntimeException('Nie udało się utworzyć relacji.');
        }
        return $rel;
    }

    public function delete(string $relationshipId, string $treeId): void
    {
        $rel = $this->relRepo->findById($relationshipId, $treeId);
        if ($rel === null) {
            throw new \InvalidArgumentException('Relacja nie istnieje.');
        }
        $this->relRepo->delete($relationshipId, $treeId);

        // Also remove the inverse
        $inverseType = $this->inverseType($rel->type);
        if ($inverseType !== null) {
            $inverse = $this->relRepo->findByPersonAndTarget($rel->personBId, $rel->personAId, $inverseType, $treeId);
            if ($inverse !== null) {
                $this->relRepo->delete($inverse->id, $treeId);
            }
        }
    }

    /** @return Relationship[] */
    public function getForPerson(string $personId, string $treeId): array
    {
        return $this->relRepo->findByPerson($personId, $treeId);
    }

    private function inverseType(string $type): ?string
    {
        return match($type) {
            'parent'  => 'child',
            'child'   => 'parent',
            'spouse'  => 'spouse',
            'sibling' => 'sibling',
            'partner' => 'partner',
            default   => null,
        };
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
