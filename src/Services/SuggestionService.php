<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;

class SuggestionService
{
    public function __construct(
        private readonly RelationshipRepository $relRepo,
        private readonly PersonRepository       $personRepo,
    ) {}

    /**
     * Analyse the existing relationships of $personId and infer missing ones.
     *
     * Returns array of:
     *   ['type' => string, 'targetPerson' => Person, 'reason' => string]
     *
     * Convention used here (FORM-interpretation, matches the form labels and the rest
     * of the codebase after the 2026-04-07 refactor):
     *
     *   ('parent', A, B) = "A has B as a parent"  → B is A's parent
     *   ('child',  A, B) = "A has B as a child"   → B is A's child
     *   ('sibling',A, B) = symmetric
     *   ('spouse', A, B) = symmetric
     *
     * `findByPerson(X)` returns rows where X = personA, so the joined "other" is in
     * personB and the row's type tells us what role personB plays for X.
     *
     * Rules:
     *  sibling(me,B)        → parents of B → my parents; other siblings of B → my siblings
     *  spouse/partner(me,B) → children of B → my children
     *  child(me,B)          → siblings of B that share me as parent → my children;
     *                         B's other parent → my spouse
     *  parent(me,B)         → B's other children → my siblings; B's spouse → my other parent
     */
    public function compute(string $personId, string $treeId): array
    {
        $myRels = $this->relRepo->findByPerson($personId, $treeId);
        $seen   = [];  // dedup key: "type|targetPersonId"
        $result = [];

        // Memoise findByPerson() to avoid the obvious N+1 — a single compute() can
        // touch the same neighbour many times when families are dense.
        $relsCache = [$personId => $myRels];
        $relsOf = function (string $id) use (&$relsCache, $treeId): array {
            return $relsCache[$id] ??= $this->relRepo->findByPerson($id, $treeId);
        };

        foreach ($myRels as $rel) {
            $otherId   = $rel->personBId;
            $otherRels = $relsOf($otherId);

            switch ($rel->type) {
                case 'sibling':
                    foreach ($otherRels as $or) {
                        // Parents of sibling → suggest as my parents
                        if ($or->type === 'parent') {
                            $candidateId = $or->personBId;
                            // Skip if this candidate is a child of any of my siblings
                            $isChildOfSibling = false;
                            foreach ($myRels as $myRel) {
                                if ($myRel->type === 'sibling'
                                    && $this->relRepo->exists($myRel->personBId, $candidateId, 'child', $treeId)) {
                                    $isChildOfSibling = true;
                                    break;
                                }
                            }
                            if (!$isChildOfSibling) {
                                $this->addSuggestion(
                                    $result, $seen, $personId, $treeId,
                                    'parent', $candidateId,
                                    'rodzic rodzeństwa ' . $this->relatedName($rel),
                                );
                            }
                        }
                        // Other siblings of sibling → suggest as my siblings
                        if ($or->type === 'sibling' && $or->personBId !== $personId) {
                            $candidateSibId = $or->personBId;
                            // Skip if candidate is a parent of any of my parents (= grandparent)
                            $isGrandparent = false;
                            foreach ($myRels as $myRel) {
                                if ($myRel->type === 'parent'
                                    && $this->relRepo->exists($myRel->personBId, $candidateSibId, 'parent', $treeId)) {
                                    $isGrandparent = true;
                                    break;
                                }
                            }
                            if (!$isGrandparent) {
                                $this->addSuggestion(
                                    $result, $seen, $personId, $treeId,
                                    'sibling', $candidateSibId,
                                    'rodzeństwo ' . $this->relatedName($rel),
                                );
                            }
                        }
                    }
                    break;

                case 'spouse':
                case 'partner':
                    foreach ($otherRels as $or) {
                        // Children of spouse → suggest as my children
                        if ($or->type === 'child') {
                            $this->addSuggestion(
                                $result, $seen, $personId, $treeId,
                                'child', $or->personBId,
                                'dziecko ' . $this->spouseLabel($rel->type) . ' ' . $this->relatedName($rel),
                            );
                        }
                    }
                    break;

                case 'child':
                    // $otherId = B = my child
                    foreach ($otherRels as $or) {
                        // B's siblings → suggest as my children, but ONLY when the sibling
                        // already has me (`$personId`) as a parent — otherwise the sibling
                        // could be from B's other parent's previous relationship.
                        if ($or->type === 'sibling') {
                            $candidateChildId = $or->personBId;
                            if ($this->relRepo->exists($candidateChildId, $personId, 'parent', $treeId)) {
                                $this->addSuggestion(
                                    $result, $seen, $personId, $treeId,
                                    'child', $candidateChildId,
                                    'rodzeństwo dziecka ' . $this->relatedName($rel),
                                );
                            }
                        }
                        // B's other parent → suggest as my spouse
                        if ($or->type === 'parent' && $or->personBId !== $personId) {
                            $this->addSuggestion(
                                $result, $seen, $personId, $treeId,
                                'spouse', $or->personBId,
                                'rodzic dziecka ' . $this->relatedName($rel),
                            );
                        }
                    }
                    break;

                case 'parent':
                    // $otherId = B = my parent
                    foreach ($otherRels as $or) {
                        // B's other children (excluding me) → suggest as my siblings
                        if ($or->type === 'child' && $or->personBId !== $personId) {
                            $candidateChildId = $or->personBId;
                            // Skip if candidate is also a parent of B (= my grandparent)
                            if (!$this->relRepo->exists($otherId, $candidateChildId, 'parent', $treeId)) {
                                $this->addSuggestion(
                                    $result, $seen, $personId, $treeId,
                                    'sibling', $candidateChildId,
                                    'dziecko rodzica ' . $this->relatedName($rel),
                                );
                            }
                        }
                        // B's spouse → suggest as my other parent
                        if (in_array($or->type, ['spouse', 'partner'], true)) {
                            $this->addSuggestion(
                                $result, $seen, $personId, $treeId,
                                'parent', $or->personBId,
                                $this->spouseLabel($or->type) . ' rodzica ' . $this->relatedName($rel),
                            );
                        }
                    }
                    break;
            }
        }

        return $result;
    }

    private function addSuggestion(
        array  &$result,
        array  &$seen,
        string $personId,
        string $treeId,
        string $type,
        string $targetPersonId,
        string $reason,
    ): void {
        if ($targetPersonId === $personId) {
            return;
        }

        $key = $type . '|' . $targetPersonId;
        if (isset($seen[$key])) {
            return;
        }

        // Skip if relationship already exists
        if ($this->relRepo->exists($personId, $targetPersonId, $type, $treeId)) {
            return;
        }

        $targetPerson = $this->personRepo->findById($targetPersonId, $treeId);
        if ($targetPerson === null) {
            return;
        }

        $seen[$key] = true;
        $result[]   = [
            'type'         => $type,
            'targetPerson' => $targetPerson,
            'reason'       => $reason,
        ];
    }

    private function relatedName(\App\Models\Relationship $rel): string
    {
        return $rel->relatedFullName();
    }

    private function spouseLabel(string $type): string
    {
        return $type === 'partner' ? 'partnera/partnerki' : 'małżonka/małżonki';
    }
}
