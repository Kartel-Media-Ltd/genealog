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
        $personA = $this->personRepo->findById($personAId, $treeId);
        if ($personA === null) {
            throw new \InvalidArgumentException('Pierwsza osoba nie istnieje w tym drzewie.');
        }
        $personB = $this->personRepo->findById($personBId, $treeId);
        if ($personB === null) {
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

        // Sanity check for parent/child: parent must be older than child (when both dates known).
        // App convention: ('parent', A, B) = "A has B as parent" → B is parent, A is child
        //                 ('child',  A, B) = "A has B as child"  → A is parent, B is child
        if (in_array($type, ['parent', 'child'], true)) {
            $parent = $type === 'parent' ? $personB : $personA;
            $child  = $type === 'parent' ? $personA : $personB;
            // Compare birth years as integers — string compare on Y-m-d would work,
            // but breaks if a date is stored as bare "1850" or with a different format.
            $parentYear = $parent->birthDate !== null ? (int) substr($parent->birthDate, 0, 4) : null;
            $childYear  = $child->birthDate  !== null ? (int) substr($child->birthDate,  0, 4) : null;
            if ($parentYear !== null && $childYear !== null && $parentYear > $childYear) {
                throw new \InvalidArgumentException(sprintf(
                    'Nie można dodać relacji: %s (ur. %d) jest młodszy/a niż %s (ur. %d) i nie może być rodzicem.',
                    $parent->fullName(), $parentYear,
                    $child->fullName(),  $childYear,
                ));
            }
            // Reject creating a cycle: opposite-direction parent/child for the same pair.
            $opposite = $type === 'parent' ? 'child' : 'parent';
            if ($this->relRepo->exists($personAId, $personBId, $opposite, $treeId)
                || $this->relRepo->exists($personBId, $personAId, $type, $treeId)) {
                throw new \InvalidArgumentException(sprintf(
                    'Sprzeczna relacja: między %s i %s istnieje już odwrotne pokrewieństwo.',
                    $personA->fullName(), $personB->fullName(),
                ));
            }
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
