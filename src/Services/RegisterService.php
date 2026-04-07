<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;

class RegisterService
{
    public function __construct(
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
    ) {}

    /**
     * Build a descendant register starting from $personId.
     *
     * Returns array of entries (root is first with number='', depth=0):
     *   ['number' => '1.2', 'person' => Person, 'depth' => int,
     *    'spouses' => Person[], 'note' => ?string, 'skipped' => bool]
     *
     * Modes:
     *  - 'cognatic':    all descendants regardless of gender
     *  - 'patrilinear': daughters shown with spouse note but not expanded further
     */
    public function build(string $personId, string $treeId, string $mode = 'cognatic'): array
    {
        $result  = [];
        $visited = [];

        $traverse = null;
        $traverse = function (string $pid, string $prefix, int $depth) use (
            &$traverse, &$result, &$visited, $treeId, $mode
        ): void {
            if (isset($visited[$pid])) {
                return;
            }
            $visited[$pid] = true;

            $person = $this->personRepo->findById($pid, $treeId);
            if ($person === null) {
                return;
            }

            $spouses = $this->findSpouses($pid, $treeId);
            $note    = null;
            $skipped = false;

            // Patrilinear: daughters are listed but not expanded; add spouse note
            if ($mode === 'patrilinear' && $depth > 0 && $person->gender === 'female') {
                $spouseNames = array_map(fn(Person $s) => $s->fullName(), $spouses);
                if (!empty($spouseNames)) {
                    $note = 'zamężna za ' . implode(', ', $spouseNames);
                }
                $skipped = true;
                $spouses = [];
            }

            $result[] = [
                'number'  => $prefix,
                'person'  => $person,
                'depth'   => $depth,
                'spouses' => $spouses,
                'note'    => $note,
                'skipped' => $skipped,
            ];

            if (!$skipped) {
                $children = $this->findChildren($pid, $treeId);
                $i = 1;
                foreach ($children as $child) {
                    $childNumber = $prefix === '' ? (string)$i : "$prefix.$i";
                    $traverse($child->id, $childNumber, $depth + 1);
                    $i++;
                }
            }
        };

        $traverse($personId, '', 0);

        return $result;
    }

    /** @return Person[] children of $personId, sorted by birth year then name */
    private function findChildren(string $personId, string $treeId): array
    {
        $rels     = $this->relRepo->findByPerson($personId, $treeId);
        $children = [];

        foreach ($rels as $rel) {
            if ($rel->type !== 'child') {
                continue;
            }
            $child = $this->personRepo->findById($rel->personBId, $treeId);
            if ($child !== null) {
                $children[] = $child;
            }
        }

        usort($children, function (Person $a, Person $b): int {
            $aYear = $a->birthDate ? (int)substr($a->birthDate, 0, 4) : 9999;
            $bYear = $b->birthDate ? (int)substr($b->birthDate, 0, 4) : 9999;
            return $aYear !== $bYear
                ? $aYear <=> $bYear
                : strcmp($a->fullName(), $b->fullName());
        });

        return $children;
    }

    /** @return Person[] spouses and partners of $personId */
    private function findSpouses(string $personId, string $treeId): array
    {
        $rels    = $this->relRepo->findByPerson($personId, $treeId);
        $spouses = [];

        foreach ($rels as $rel) {
            if (!in_array($rel->type, ['spouse', 'partner'], true)) {
                continue;
            }
            $spouse = $this->personRepo->findById($rel->personBId, $treeId);
            if ($spouse !== null) {
                $spouses[] = $spouse;
            }
        }

        return $spouses;
    }
}
