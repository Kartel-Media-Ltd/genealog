<?php
declare(strict_types=1);

namespace App\Services\Discovery\DTO;

/**
 * Pojedynczy wynik wyszukiwania — znormalizowany format z dowolnego źródła.
 * Bezpieczny do serializacji JSON dla frontendu (autosuggest panel).
 *
 * Cross-tree wyniki MUSZĄ być anonimizowane (bez photo, notes, full date,
 * owner_email, person_id, tree_id, tree_name) — dopuszczalne: firstName,
 * lastName, birthYear (rok, nie full date), region, treeRef (hash).
 */
final class MatchResult implements \JsonSerializable
{
    /** Suggestion: human-readable labels per source type — single source of truth (no duplikacja w UI) */
    private const SOURCE_LABELS = [
        'local'        => 'Twoje drzewo',
        'cross_tree'   => 'Inne drzewo (anonimowe)',
        'familysearch' => 'FamilySearch',
        'genetyka'     => 'Geneteka (PTG)',
        'external'     => 'Zewnętrzna baza',
    ];

    public function __construct(
        public readonly string  $sourceType,   // 'local' | 'cross_tree' | 'external'
        public readonly string  $sourceId,     // persons.id | gpi.id | external system id
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly ?int    $birthYear,
        public readonly ?string $birthPlace,   // pełne miejsce TYLKO dla local (user ma dostęp)
        public readonly ?string $region,       // tylko region (województwo) — cross-tree
        public readonly float   $confidence,   // 0.0 - 1.0
        public readonly ?string $treeRef       = null, // "Drzewo #XYZW" dla cross-tree
        public readonly ?string $treeName      = null, // pełna nazwa dla local
        public readonly ?string $treeId        = null, // TYLKO dla local
        public readonly ?int    $deathYear     = null,
        public readonly ?string $gender        = null,
        public readonly ?string $externalUrl   = null, // link do profilu w external systemie
    ) {
        // Nit guard: cross-tree NIE może mieć birthPlace ani treeName/treeId
        // (anonimizacja RODO Art. 25 — Privacy by Design enforced w konstruktorze).
        if ($sourceType === 'cross_tree') {
            if ($birthPlace !== null) {
                throw new \InvalidArgumentException(
                    'MatchResult: cross_tree source nie może mieć birthPlace (RODO anonimizacja)'
                );
            }
            if ($treeName !== null || $treeId !== null) {
                throw new \InvalidArgumentException(
                    'MatchResult: cross_tree source nie może ujawniać treeName/treeId'
                );
            }
        }
    }

    /** Suggestion: human-readable label dla source type (eliminuje duplikację w UI) */
    public function sourceLabel(): string
    {
        return self::SOURCE_LABELS[$this->sourceType] ?? $this->sourceType;
    }

    public function jsonSerialize(): array
    {
        $data = [
            'sourceType'  => $this->sourceType,
            'sourceLabel' => $this->sourceLabel(),
            'sourceId'    => $this->sourceId,
            'firstName'   => $this->firstName,
            'lastName'    => $this->lastName,
            'birthYear'   => $this->birthYear,
            'confidence'  => round($this->confidence, 2),
        ];

        if ($this->sourceType === 'local') {
            // Pełne dane — user ma dostęp do tego drzewa
            $data['birthPlace'] = $this->birthPlace;
            $data['treeName']   = $this->treeName;
            $data['treeId']     = $this->treeId;
            $data['deathYear']  = $this->deathYear;
            $data['gender']     = $this->gender;
            return $data;
        }

        if ($this->sourceType === 'cross_tree') {
            // Anonimowe dane — TYLKO rok, region, anonimowy ref do drzewa
            $data['region']  = $this->region;
            $data['treeRef'] = $this->treeRef;
            return $data;
        }

        // external — zależy od źródła
        $data['birthPlace']  = $this->birthPlace;
        $data['deathYear']   = $this->deathYear;
        $data['gender']      = $this->gender;
        $data['externalUrl'] = $this->externalUrl;
        return $data;
    }
}
