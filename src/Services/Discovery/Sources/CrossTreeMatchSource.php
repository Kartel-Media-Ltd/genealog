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
 * Source: osoby z innych drzew (innych userów) przez global_person_index.
 *
 * Privacy (RODO):
 *   - Nie zwraca person_id, tree_id, owner_email, photo, notes, pełnej daty
 *   - Zamiast tree_name zwraca anonimowy tree_ref (hash) — audyt P4
 *   - Wyklucza drzewa do których user sam ma dostęp (szukanie tylko u obcych)
 *   - Tylko rok urodzenia + region (województwo) + imię/nazwisko
 */
final class CrossTreeMatchSource implements MatchSourceInterface
{
    private const LIMIT = 20;

    /** B6: soundex-only match dla popularnych nazwisk = mnóstwo false positives.
     *  Confidence pod progiem MIN_CONFIDENCE w MatchingService → nie persistowane. */
    private const CONFIDENCE_EXACT          = 1.0;
    private const CONFIDENCE_SOUNDEX_WITH_YEAR = 0.55;
    private const CONFIDENCE_SOUNDEX_ONLY   = 0.30; // poniżej MIN_CONFIDENCE_TO_PERSIST

    /** ±20 lat dla soundex-only — eliminuje matche w odległych pokoleniach */
    private const BIRTH_YEAR_TOLERANCE = 20;

    public function __construct(
        private readonly Database           $db,
        private readonly FingerprintService $fingerprint,
    ) {}

    public function getName(): string
    {
        return MatchSourceInterface::SOURCE_CROSS_TREE;
    }

    public function isAvailable(): bool
    {
        // Dostępne jeśli istnieje tabela (zawsze po migracji 008).
        return true;
    }

    public function getTimeoutSeconds(): int
    {
        return 5; // local DB query (global_person_index)
    }

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        // I6 fix: guard na pustego userId (gdy session wygasła w tle)
        if ($context->currentUserId === '') {
            return [];
        }

        $hash    = $this->fingerprint->compute(
            $criteria->firstName,
            $criteria->lastName,
            $criteria->birthYear,
            $criteria->birthPlace,
        );
        $soundex = $this->fingerprint->computeSoundex($criteria->firstName, $criteria->lastName);

        if ($hash === null && $soundex === null) {
            return [];
        }

        // Wykluczamy drzewa do których user sam ma dostęp — szukamy tylko u obcych.
        // Dodatkowo wykluczamy własne drzewa ownera (dla bezpieczeństwa przy zmianach ról).
        $excludeTreeIds = $context->accessibleTreeIds;

        $conditions = [];
        $binds      = [];

        if ($hash !== null) {
            $conditions[] = 'gpi.fingerprint_hash = ?';
            $binds[]      = $hash;
        }
        if ($soundex !== null) {
            $conditions[] = 'gpi.name_soundex = ?';
            $binds[]      = $soundex;
        }

        $whereMatch = '(' . implode(' OR ', $conditions) . ')';
        // Zawsze wyklucz osoby z własnych drzew oraz wpisy należące do ownera=obecnego usera
        $whereExclude = 'gpi.owner_user_id != ?';
        $binds[]      = $context->currentUserId;

        if (!empty($excludeTreeIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeTreeIds), '?'));
            $whereExclude .= " AND gpi.tree_id NOT IN ($placeholders)";
            $binds         = array_merge($binds, $excludeTreeIds);
        }

        // B6 fix: gdy user podał birth_year, zawężamy soundex match do ±20 lat.
        // Dla popularnych "Kowalski" eliminuje matche z innych pokoleń.
        $birthYearFilter = '';
        if ($criteria->birthYear !== null && $hash === null) {
            $birthYearFilter = ' AND gpi.earliest_birth_year BETWEEN ? AND ?';
            $binds[] = $criteria->birthYear - self::BIRTH_YEAR_TOLERANCE;
            $binds[] = $criteria->birthYear + self::BIRTH_YEAR_TOLERANCE;
        }

        // Opcjonalny filtr gender — gdy podano i nie jest 'unknown'
        $genderFilter = '';
        if ($criteria->gender !== null && $criteria->gender !== 'unknown') {
            $genderFilter = ' AND (gpi.gender = ? OR gpi.gender = \'unknown\')';
            $binds[]      = $criteria->gender;
        }

        // I9: bierzemy first_name/last_name bezpośrednio z global_person_index (immutable)
        // zamiast JOINować tabelę `persons` która jest mutowalna. To zapobiega ujawnieniu
        // aktualnych wartości gdy user zmieni visibility na private przed unindex'em.
        $sql = "SELECT gpi.id AS gpi_id,
                       gpi.fingerprint_hash,
                       gpi.name_soundex,
                       gpi.tree_id,
                       gpi.region,
                       gpi.earliest_birth_year AS birth_year,
                       gpi.first_name, gpi.last_name,
                       gpi.gender
                FROM global_person_index gpi
                WHERE $whereMatch AND $whereExclude{$birthYearFilter}{$genderFilter}
                  AND gpi.first_name IS NOT NULL
                LIMIT " . self::LIMIT;

        $rows = $this->db->fetchAll($sql, $binds);

        $targetHash = $hash;

        $out = [];
        foreach ($rows as $row) {
            $isExact = $targetHash !== null && $row['fingerprint_hash'] === $targetHash;

            // B6: confidence zależnie od jakości matchu
            // - exact (full fingerprint match)        = 1.0
            // - soundex + birth_year w zakresie       = 0.55 (powyżej MIN persist)
            // - soundex bez birth_year                = 0.30 (poniżej MIN — nie persistowane)
            if ($isExact) {
                $confidence = self::CONFIDENCE_EXACT;
            } elseif ($criteria->birthYear !== null) {
                $confidence = self::CONFIDENCE_SOUNDEX_WITH_YEAR;
            } else {
                $confidence = self::CONFIDENCE_SOUNDEX_ONLY;
            }

            // Bonus +0.05 gdy płeć się zgadza (oba pola znane i zgodne)
            if ($criteria->gender !== null
                && $criteria->gender !== 'unknown'
                && isset($row['gender'])
                && $row['gender'] === $criteria->gender
            ) {
                $confidence = min(1.0, $confidence + 0.05);
            }

            // I8 fix: 8 znaków HMAC (z user secret) zamiast 4 znaków sha256
            // - 8 znaków = 4.3 mld możliwości (vs 16k przy 4)
            // - HMAC z user secret = atakujący nie wyliczy hash dla cudzego drzewa
            $treeRef = 'Drzewo #' . substr(
                hash_hmac('sha256', $row['tree_id'], $context->currentUserId),
                0,
                8
            );

            $out[] = new MatchResult(
                sourceType: 'cross_tree',
                sourceId:   (string)$row['gpi_id'],
                firstName:  (string)$row['first_name'],
                lastName:   (string)$row['last_name'],
                birthYear:  $row['birth_year'] !== null ? (int)$row['birth_year'] : null,
                birthPlace: null,
                region:     $row['region'] !== null ? (string)$row['region'] : null,
                confidence: $confidence,
                treeRef:    $treeRef,
                treeName:   null,
                treeId:     null,
                isDead:     true, // GlobalIndex indeksuje wyłącznie is_living=0
            );
        }

        // Sortuj po confidence malejąco
        usort($out, static fn(MatchResult $a, MatchResult $b) => $b->confidence <=> $a->confidence);

        // ZAD-2.4 (P4) + ZAD-3.10 (D10): debug log BEZ userId (info disclosure).
        // Dodatkowo sampling 1% — chroni log przed spamem przy popularnych nazwiskach.
        if (count($out) > 0 && random_int(1, 100) === 1) {
            error_log(sprintf(
                '[CrossTreeMatchSource] %d matches (hash=%s, sampled 1%%)',
                count($out),
                $hash !== null ? 'yes' : 'no'
            ));
        }

        return $out;
    }
}
