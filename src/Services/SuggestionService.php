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
     * Rules:
     *  sibling(A,B)         → parents of B (B has type='parent' rows) become candidate parents of A
     *                       → other siblings of B become candidate siblings of A
     *  spouse/partner(A,B)  → children of B (B has type='child' rows) become candidate children of A
     *  child(A,B i.e. B is A's child) → siblings of B become candidate children of A
     *                                  → B's other parent becomes candidate spouse of A
     *  parent(A,B i.e. B is A's parent) → B's other children become siblings of A
     *                                    → B's spouse becomes A's other parent
     */
    public function compute(string $personId, string $treeId): array
    {
        $myRels    = $this->relRepo->findByPerson($personId, $treeId);
        $seen      = [];  // dedup key: "type|targetPersonId"
        $result    = [];

        foreach ($myRels as $rel) {
            $otherId = $rel->personBId;
            $otherRels = $this->relRepo->findByPerson($otherId, $treeId);

            switch ($rel->type) {
                case 'sibling':
                    foreach ($otherRels as $or) {
                        // Parents of sibling → suggest as my parents
                        // type='parent' in findByPerson(B): B has parent personBId
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
                            // Skip if candidate is a parent of any of my parents (= grandparent, not sibling)
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
                        // type='child' in findByPerson(B): B has child personBId
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
                    // $otherId = B = A's child
                    // type='sibling' in findByPerson(B): B has sibling personBId → suggest as A's other child
                    foreach ($otherRels as $or) {
                        if ($or->type === 'sibling') {
                            $this->addSuggestion(
                                $result, $seen, $personId, $treeId,
                                'child', $or->personBId,
                                'rodzeństwo dziecka ' . $this->relatedName($rel),
                            );
                        }
                        // B's other parent → suggest as A's spouse
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
                    // $otherId is my parent — their other children are my siblings
                    // type='child' in findByPerson(B): B has child personBId
                    foreach ($otherRels as $or) {
                        if ($or->type === 'child' && $or->personBId !== $personId) {
                            $candidateChildId = $or->personBId;
                            // Skip if candidate is also a parent of $otherId (my parent) —
                            // that would make them my grandparent, not sibling
                            if (!$this->relRepo->exists($otherId, $candidateChildId, 'parent', $treeId)) {
                                $this->addSuggestion(
                                    $result, $seen, $personId, $treeId,
                                    'sibling', $candidateChildId,
                                    'dziecko rodzica ' . $this->relatedName($rel),
                                );
                            }
                        }
                        // Parent's spouse → suggest as my other parent
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
