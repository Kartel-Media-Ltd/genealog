<?php
declare(strict_types=1);

namespace App\Services\Discovery\Sources;

use App\Core\Database;
use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\Discovery\FingerprintService;
use App\Services\Discovery\MatchSourceInterface;

/**
 * Source: osoby z drzew do których user ma dostęp (owner/editor/viewer).
 *
 * Strategia hybrid:
 *   1. exact fingerprint match → confidence 1.0
 *   2. jeśli <3 trafień → fuzzy przez name_soundex + PHP levenshtein → confidence 0.5-0.9
 *
 * Zwraca pełne dane (birthPlace, treeName, treeId) bo user ma dostęp do tych drzew.
 */
final class LocalTreeMatchSource implements MatchSourceInterface
{
    private const EXACT_LIMIT = 10;
    private const FUZZY_LIMIT = 20;
    private const MIN_FUZZY_CONFIDENCE = 0.5;

    public function __construct(
        private readonly Database           $db,
        private readonly FingerprintService $fingerprint,
    ) {}

    public function getName(): string
    {
        return 'local';
    }

    public function isAvailable(): bool
    {
        return true; // zawsze dostępne — to lokalna baza
    }

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        if (empty($context->accessibleTreeIds)) {
            return [];
        }

        // Phase 1: exact fingerprint
        $results = $this->exactByFingerprint($criteria, $context);
        if (count($results) >= 3) {
            return $results;
        }

        // Phase 2: fuzzy (soundex + levenshtein)
        $fuzzy   = $this->fuzzyByName($criteria, $context, $results);
        $results = array_merge($results, $fuzzy);

        return $results;
    }

    /**
     * @return list<MatchResult>
     */
    private function exactByFingerprint(SearchCriteria $criteria, SearchContext $context): array
    {
        $hash = $this->fingerprint->compute(
            $criteria->firstName,
            $criteria->lastName,
            $criteria->birthYear,
            $criteria->birthPlace,
        );
        if ($hash === null) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($context->accessibleTreeIds), '?'));
        $binds        = array_merge([$hash], $context->accessibleTreeIds);

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.birth_place,
                    p.death_date, p.gender, p.tree_id,
                    t.name AS tree_name
             FROM persons p
             JOIN trees t ON t.id = p.tree_id
             WHERE p.fingerprint_hash = ?
               AND p.tree_id IN ($placeholders)
             LIMIT " . self::EXACT_LIMIT,
            $binds
        );

        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->rowToResult($row, 1.0);
        }
        return $out;
    }

    /**
     * @param list<MatchResult> $existing lista już zwróconych (do deduplikacji po id)
     * @return list<MatchResult>
     */
    private function fuzzyByName(SearchCriteria $criteria, SearchContext $context, array $existing): array
    {
        $soundex = $this->fingerprint->computeSoundex($criteria->firstName, $criteria->lastName);
        if ($soundex === null) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($context->accessibleTreeIds), '?'));
        $binds        = array_merge([$soundex], $context->accessibleTreeIds);

        $rows = $this->db->fetchAll(
            "SELECT p.id, p.first_name, p.last_name, p.birth_date, p.birth_place,
                    p.death_date, p.gender, p.tree_id,
                    t.name AS tree_name
             FROM persons p
             JOIN trees t ON t.id = p.tree_id
             WHERE p.name_soundex = ?
               AND p.tree_id IN ($placeholders)
             LIMIT " . self::FUZZY_LIMIT,
            $binds
        );

        $existingIds = array_flip(array_map(static fn($r) => $r->sourceId, $existing));
        $out         = [];

        foreach ($rows as $row) {
            if (isset($existingIds[$row['id']])) {
                continue;
            }
            $confidence = $this->computeLevenshteinConfidence($criteria, $row);
            if ($confidence < self::MIN_FUZZY_CONFIDENCE) {
                continue;
            }
            $out[] = $this->rowToResult($row, $confidence);
        }

        // Sortuj malejąco po confidence
        usort($out, static fn($a, $b) => $b->confidence <=> $a->confidence);
        return $out;
    }

    private function computeLevenshteinConfidence(SearchCriteria $criteria, array $row): float
    {
        // Transliteracja przed byte-based levenshtein — polskie diakrytyki (UTF-8,
        // 2 bajty) nie liczą się jako 2 zmiany w porównaniu do ASCII (audyt I4).
        $fromFirst = $this->fingerprint->asciiNormalize($criteria->firstName);
        $fromLast  = $this->fingerprint->asciiNormalize($criteria->lastName);
        $toFirst   = $this->fingerprint->asciiNormalize((string)$row['first_name']);
        $toLast    = $this->fingerprint->asciiNormalize((string)$row['last_name']);

        $firstScore = $this->similarity($fromFirst, $toFirst);
        $lastScore  = $this->similarity($fromLast, $toLast);

        // Wagi: nazwisko ważniejsze (0.6) od imienia (0.4)
        $score = ($firstScore * 0.4) + ($lastScore * 0.6);

        // Bonus/penalty za różnicę roku urodzenia
        // Important #10 nit fix: dodajemy płynne przejście dla 3-10 lat (zamiast neutralnego gap)
        if ($criteria->birthYear !== null && !empty($row['birth_date'])) {
            $rowYear = (int)substr((string)$row['birth_date'], 0, 4);
            $diff    = abs($rowYear - $criteria->birthYear);
            if ($diff <= 2) {
                $score = min(1.0, $score + 0.1);  // +10% za bliską zgodność
            } elseif ($diff <= 10) {
                $score -= 0.05;                    // -5% za umiarkowaną różnicę
            } else {
                $score -= 0.15;                    // -15% za dużą różnicę
            }
        }

        return max(0.0, min(0.95, $score)); // cap fuzzy na 0.95, exact ma 1.0
    }

    private function similarity(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 1.0;
        }
        if ($a === '' || $b === '') {
            return 0.0;
        }
        // mb_levenshtein nie ma natywnie — używamy standardowego, działa dla ASCII-translit
        $lev    = levenshtein($a, $b);
        $maxLen = max(mb_strlen($a), mb_strlen($b));
        return $maxLen > 0 ? 1.0 - ($lev / $maxLen) : 0.0;
    }

    private function rowToResult(array $row, float $confidence): MatchResult
    {
        $birthYear = !empty($row['birth_date']) ? (int)substr((string)$row['birth_date'], 0, 4) : null;
        $deathYear = !empty($row['death_date']) ? (int)substr((string)$row['death_date'], 0, 4) : null;

        return new MatchResult(
            sourceType: 'local',
            sourceId:   (string)$row['id'],
            firstName:  (string)$row['first_name'],
            lastName:   (string)$row['last_name'],
            birthYear:  $birthYear,
            birthPlace: $row['birth_place'] !== null ? (string)$row['birth_place'] : null,
            region:     null,
            confidence: $confidence,
            treeRef:    null,
            treeName:   (string)$row['tree_name'],
            treeId:     (string)$row['tree_id'],
            deathYear:  $deathYear,
            gender:     (string)$row['gender'],
        );
    }
}
