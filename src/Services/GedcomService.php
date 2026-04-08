<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Uuid;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;

class GedcomService
{
    private string $conflictStrategy = 'skip'; // 'skip' | 'update'

    /** GEDCOM month abbreviation → 2-digit number */
    private const MONTHS = [
        'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
        'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
        'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12',
    ];

    public function __construct(
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relationshipRepo,
        private readonly \PDO                   $pdo,
    ) {}

    public function setConflictStrategy(string $strategy): void
    {
        if (in_array($strategy, ['skip', 'update'], true)) {
            $this->conflictStrategy = $strategy;
        }
    }

    // -------------------------------------------------------------------------
    // IMPORT
    // -------------------------------------------------------------------------

    public function import(int|string $treeId, string $filePath, int|string $userId): ImportResult
    {
        // ZAD-2.1 (P1): flaga wyłącza per-person matching w EventDispatcher listener
        // podczas bulk importu. Po zakończeniu emitujemy batch `tree.imported` który
        // uruchamia matching RAZ dla całego drzewa.
        $GLOBALS['_gedcom_import_in_progress'] = true;

        $success       = false;
        $personsCount  = 0;
        $relationshipsCount = 0;
        $skipped       = 0;
        $errors        = [];

        try {
            $content = file_get_contents($filePath);
            if ($content === false) {
                throw new \RuntimeException('Nie można odczytać pliku GEDCOM.');
            }

            // Normalize line endings
            $content = str_replace(["\r\n", "\r"], "\n", $content);

            // Walidacja struktury GEDCOM przed parsowaniem
            $this->validateGedcomStructure($content);

            $records = $this->parseGedcom($content);

            if (empty($records['individuals']) && empty($records['families'])) {
                throw new \RuntimeException('Plik nie zawiera żadnych osób ani rodzin (INDI/FAM).');
            }

            $this->pdo->beginTransaction();

            [$personsCount, $skipped, $xrefMap, $errors] = $this->importIndividuals(
                $records['individuals'],
                (string)$treeId,
                (string)$userId,
            );

            $relationshipsCount = $this->importFamilies(
                $records['families'],
                (string)$treeId,
                $xrefMap,
            );

            $this->pdo->commit();
            $success = true;

            return new ImportResult($personsCount, $relationshipsCount, $skipped, $errors);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        } finally {
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            // ZAD-2.1 (P1): wyłącz flagę PRZED emit tree.imported — żeby handler
            // mógł bezpiecznie wywołać findAndNotifyMatches bez natychmiastowego skip.
            unset($GLOBALS['_gedcom_import_in_progress']);

            // Emit batch event TYLKO gdy import się udał. W finally — bo `return` wewnątrz try
            // ominąłby kod po return. Używamy flagi $success żeby nie emitować przy exception.
            if ($success) {
                try {
                    \App\Core\EventDispatcher::dispatch('tree.imported', (string)$treeId, (string)$userId, $personsCount);
                } catch (\Throwable $e) {
                    error_log('[GedcomService] tree.imported dispatch failed: ' . $e->getMessage());
                }
            }
        }
    }

    /**
     * Walidacja podstawowej struktury pliku GEDCOM.
     * Sprawdza obecność `0 HEAD` i `0 TRLR` — chroni przed śmieciami i mylonymi formatami.
     *
     * @throws \RuntimeException gdy plik nie wygląda na poprawny GEDCOM
     */
    private function validateGedcomStructure(string $content): void
    {
        $maxBytes = 10 * 1024; // sprawdzamy tylko nagłówek pliku
        $head     = substr($content, 0, $maxBytes);

        if (!preg_match('/^\s*0\s+HEAD\b/m', $head)) {
            throw new \RuntimeException(
                'Nieprawidłowy format GEDCOM: brakuje nagłówka "0 HEAD" na początku pliku.'
            );
        }
        if (!preg_match('/^\s*0\s+TRLR\b/m', $content)) {
            throw new \RuntimeException(
                'Nieprawidłowy format GEDCOM: brakuje znacznika końca "0 TRLR".'
            );
        }
    }

    /**
     * Parse GEDCOM content into structured arrays.
     * Returns ['individuals' => [...], 'families' => [...]]
     */
    private function parseGedcom(string $content): array
    {
        $lines       = explode("\n", $content);
        $individuals = [];
        $families    = [];

        $currentRecord  = null;
        $currentType    = null; // 'INDI' | 'FAM'
        $currentXref    = null;
        $currentTag     = null;   // last level-1 tag
        $currentSubTag  = null;   // last level-2 tag

        foreach ($lines as $lineNo => $rawLine) {
            $line = trim($rawLine);
            if ($line === '') {
                continue;
            }

            // Parse: LEVEL [XREF] TAG [VALUE]
            if (!preg_match('/^(\d+)\s+(\S+)(?:\s+(.*))?$/', $line, $m)) {
                continue;
            }

            $level = (int)$m[1];
            $tag   = strtoupper(trim($m[2]));
            $value = isset($m[3]) ? trim($m[3]) : '';

            // Level 0 — new record or HEAD/TRLR
            if ($level === 0) {
                // Save previous record
                if ($currentRecord !== null && $currentXref !== null) {
                    if ($currentType === 'INDI') {
                        $individuals[$currentXref] = $currentRecord;
                    } elseif ($currentType === 'FAM') {
                        $families[$currentXref] = $currentRecord;
                    }
                }

                $currentRecord = null;
                $currentType   = null;
                $currentXref   = null;
                $currentTag    = null;
                $currentSubTag = null;

                // e.g.  0 @I1@ INDI   or   0 @F1@ FAM
                if (preg_match('/^@([^@]+)@$/', $tag, $xm)) {
                    $xref        = '@' . $xm[1] . '@';
                    $recordType  = strtoupper($value);
                    if (in_array($recordType, ['INDI', 'FAM'], true)) {
                        $currentXref   = $xref;
                        $currentType   = $recordType;
                        $currentRecord = [
                            'xref'        => $xref,
                            'tags'        => [],
                        ];
                    }
                }
                continue;
            }

            if ($currentRecord === null) {
                continue;
            }

            if ($level === 1) {
                $currentTag    = $tag;
                $currentSubTag = null;
                $currentRecord['tags'][$tag]   = $currentRecord['tags'][$tag] ?? [];
                $currentRecord['tags'][$tag][] = ['value' => $value, 'sub' => []];
            } elseif ($level === 2 && $currentTag !== null) {
                $idx = count($currentRecord['tags'][$currentTag]) - 1;

                // CONT/CONC — append to parent level-1 value (don't treat as sub-tag)
                if (in_array($tag, ['CONT', 'CONC'], true)) {
                    $sep = ($tag === 'CONT') ? "\n" : '';
                    $currentRecord['tags'][$currentTag][$idx]['value'] .= $sep . $value;
                    $currentSubTag = null;
                } else {
                    $currentSubTag = $tag;
                    $currentRecord['tags'][$currentTag][$idx]['sub'][$tag]   =
                        $currentRecord['tags'][$currentTag][$idx]['sub'][$tag] ?? [];
                    $currentRecord['tags'][$currentTag][$idx]['sub'][$tag][] = $value;
                }
            } elseif ($level === 3 && $currentTag !== null && $currentSubTag !== null) {
                // Level 3 CONT/CONC — append to last sub-value
                if (in_array($tag, ['CONT', 'CONC'], true)) {
                    $idx1   = count($currentRecord['tags'][$currentTag]) - 1;
                    $subArr = &$currentRecord['tags'][$currentTag][$idx1]['sub'][$currentSubTag];
                    if (isset($subArr)) {
                        $subIdx   = count($subArr) - 1;
                        $sep      = ($tag === 'CONT') ? "\n" : '';
                        $subArr[$subIdx] .= $sep . $value;
                    }
                }
            }
        }

        // Save last record
        if ($currentRecord !== null && $currentXref !== null) {
            if ($currentType === 'INDI') {
                $individuals[$currentXref] = $currentRecord;
            } elseif ($currentType === 'FAM') {
                $families[$currentXref] = $currentRecord;
            }
        }

        return ['individuals' => $individuals, 'families' => $families];
    }

    /**
     * @return array{int, int, array<string,string>, string[]}
     *   [personsInserted, skipped, xrefMap, errors]
     */
    private function importIndividuals(array $individuals, string $treeId, string $userId): array
    {
        $personsCount = 0;
        $skipped      = 0;
        $xrefMap      = [];
        $errors       = [];

        foreach ($individuals as $xref => $record) {
            try {
                $data = $this->mapIndiToPersonData($record, $treeId, $userId);

                // Check for duplicate xref
                $existing = $this->personRepo->findByXref($treeId, $xref);

                if ($existing !== null) {
                    if ($this->conflictStrategy === 'update') {
                        $this->personRepo->update($existing->id, $treeId, $data);
                        $xrefMap[$xref] = $existing->id;
                    } else {
                        $xrefMap[$xref] = $existing->id;
                        $skipped++;
                    }
                    continue;
                }

                $newId = Uuid::generate();
                $this->personRepo->create($newId, $treeId, $userId, $data);
                $xrefMap[$xref] = $newId;
                $personsCount++;
            } catch (\Throwable $e) {
                $errors[] = "Błąd przy osobie {$xref}: " . $e->getMessage();
            }
        }

        return [$personsCount, $skipped, $xrefMap, $errors];
    }

    private function mapIndiToPersonData(array $record, string $treeId, string $userId): array
    {
        $tags = $record['tags'];

        // --- NAME parsing ---
        $firstName = 'Nieznane';
        $lastName  = '';

        $nameEntries = $tags['NAME'] ?? [];
        if (!empty($nameEntries)) {
            $nameValue = $nameEntries[0]['value'] ?? '';
            // Format: "Imię /Nazwisko/" or just "Imię Nazwisko"
            if (preg_match('/^(.*?)\s*\/([^\/]*)\/$/', $nameValue, $nm)) {
                $firstName = trim($nm[1]);
                $lastName  = trim($nm[2]);
            } else {
                $parts     = explode(' ', $nameValue, 2);
                $firstName = $parts[0] ?? 'Nieznane';
                $lastName  = $parts[1] ?? '';
            }
            // GIVN overrides
            $givn = $nameEntries[0]['sub']['GIVN'][0] ?? null;
            if ($givn !== null && $givn !== '') {
                $firstName = trim($givn);
            }
            // SURN overrides
            $surn = $nameEntries[0]['sub']['SURN'][0] ?? null;
            if ($surn !== null && $surn !== '') {
                $lastName = trim($surn);
            }
        }

        if ($firstName === '') {
            $firstName = 'Nieznane';
        }

        // --- SEX → gender ---
        $sexValue = $tags['SEX'][0]['value'] ?? 'U';
        $gender   = match (strtoupper($sexValue)) {
            'M'     => 'male',
            'F'     => 'female',
            default => 'unknown',
        };

        // --- BIRT ---
        $birthDate  = null;
        $birthPlace = null;
        if (!empty($tags['BIRT'])) {
            $birtSub   = $tags['BIRT'][0]['sub'] ?? [];
            $birthDate = isset($birtSub['DATE'][0])
                ? $this->parseGedcomDate($birtSub['DATE'][0])
                : null;
            $birthPlace = $birtSub['PLAC'][0] ?? null;
        }

        // --- DEAT ---
        $isLiving  = 1;
        $deathDate = null;
        $deathPlace = null;
        if (!empty($tags['DEAT'])) {
            $isLiving   = 0;
            $deatSub    = $tags['DEAT'][0]['sub'] ?? [];
            $deathDate  = isset($deatSub['DATE'][0])
                ? $this->parseGedcomDate($deatSub['DATE'][0])
                : null;
            $deathPlace = $deatSub['PLAC'][0] ?? null;
        }

        // --- NOTE (concat multiple) ---
        $notes = null;
        if (!empty($tags['NOTE'])) {
            $noteParts = array_map(fn($n) => $n['value'] ?? '', $tags['NOTE']);
            $noteParts = array_filter($noteParts, fn($n) => $n !== '');
            if (!empty($noteParts)) {
                $notes = implode("\n---\n", $noteParts);
            }
        }

        return [
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'maiden_name'  => null,
            'birth_date'   => $birthDate,
            'birth_place'  => $birthPlace,
            'death_date'   => $deathDate,
            'death_place'  => $deathPlace,
            'gender'       => $gender,
            'is_living'    => $isLiving,
            'visibility'   => 'private',
            'notes'        => $notes,
            'gedcom_xref'  => $record['xref'],
        ];
    }

    private function importFamilies(array $families, string $treeId, array $xrefMap): int
    {
        $count = 0;

        foreach ($families as $xref => $record) {
            $tags = $record['tags'];

            $husbXref = null;
            $wifeXref = null;

            if (!empty($tags['HUSB'])) {
                $raw      = $tags['HUSB'][0]['value'] ?? '';
                $husbXref = $this->normalizeXref($raw);
            }
            if (!empty($tags['WIFE'])) {
                $raw      = $tags['WIFE'][0]['value'] ?? '';
                $wifeXref = $this->normalizeXref($raw);
            }

            $husbId = $husbXref ? ($xrefMap[$husbXref] ?? null) : null;
            $wifeId = $wifeXref ? ($xrefMap[$wifeXref] ?? null) : null;

            // Spouse relationship — check both directions to avoid duplicates on re-import.
            // Spouse is symmetric, so we store both (a,b) and (b,a) just like RelationshipService::create()
            // does for UI-created spouses, so findByPerson() returns the relation from either side.
            if ($husbId && $wifeId) {
                $aId = ($husbId < $wifeId) ? $husbId : $wifeId;
                $bId = ($husbId < $wifeId) ? $wifeId : $husbId;

                if (!$this->relationshipRepo->exists($aId, $bId, 'spouse', $treeId)
                    && !$this->relationshipRepo->exists($bId, $aId, 'spouse', $treeId)) {
                    $startDate = null;
                    if (!empty($tags['MARR'])) {
                        $marrSub   = $tags['MARR'][0]['sub'] ?? [];
                        $startDate = isset($marrSub['DATE'][0])
                            ? $this->parseGedcomDate($marrSub['DATE'][0])
                            : null;
                    }
                    $this->relationshipRepo->create(
                        Uuid::generate(), $treeId, $aId, $bId, 'spouse', $startDate
                    );
                    $this->relationshipRepo->create(
                        Uuid::generate(), $treeId, $bId, $aId, 'spouse', $startDate
                    );
                    $count += 2;
                }
            }

            // Children relationships
            $childEntries = $tags['CHIL'] ?? [];
            foreach ($childEntries as $childEntry) {
                $childXref = $this->normalizeXref($childEntry['value'] ?? '');
                $childId   = $childXref ? ($xrefMap[$childXref] ?? null) : null;

                if (!$childId) {
                    continue; // xref not in import — skip
                }

                // App convention:
                //   ('parent', A, B) = "A has B as parent"  → personA is the child, personB is the parent
                //   ('child',  A, B) = "A has B as child"   → personA is the parent, personB is the child
                if ($husbId) {
                    // child has husb as parent
                    if (!$this->relationshipRepo->exists($childId, $husbId, 'parent', $treeId)) {
                        $this->relationshipRepo->create(
                            Uuid::generate(), $treeId, $childId, $husbId, 'parent'
                        );
                        $count++;
                    }
                    // husb has child as child
                    if (!$this->relationshipRepo->exists($husbId, $childId, 'child', $treeId)) {
                        $this->relationshipRepo->create(
                            Uuid::generate(), $treeId, $husbId, $childId, 'child'
                        );
                        $count++;
                    }
                }

                if ($wifeId) {
                    // child has wife as parent
                    if (!$this->relationshipRepo->exists($childId, $wifeId, 'parent', $treeId)) {
                        $this->relationshipRepo->create(
                            Uuid::generate(), $treeId, $childId, $wifeId, 'parent'
                        );
                        $count++;
                    }
                    // wife has child as child
                    if (!$this->relationshipRepo->exists($wifeId, $childId, 'child', $treeId)) {
                        $this->relationshipRepo->create(
                            Uuid::generate(), $treeId, $wifeId, $childId, 'child'
                        );
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    // -------------------------------------------------------------------------
    // EXPORT
    // -------------------------------------------------------------------------

    public function export(int|string $treeId): string
    {
        $persons       = $this->personRepo->findByTree((string)$treeId);
        $relationships = $this->relationshipRepo->findByTree((string)$treeId);

        $parts = [];
        $parts[] = $this->buildHeader();

        foreach ($persons as $person) {
            $parts[] = $this->buildIndi($person);
        }

        // Build FAM records from spouse relationships
        $famIndex  = 1;
        $spouseRels = array_filter(
            $relationships,
            fn($r) => $r->type === 'spouse'
        );

        // Index parent relationships for quick lookup: parentId => set of childIds.
        // App convention: ('parent', A, B) = "A has B as parent" → B is parent, A is child
        //                 ('child',  A, B) = "A has B as child"  → A is parent, B is child
        // RelationshipService stores both forward + inverse for each pair, so we use a
        // set keyed by childId to dedupe automatically.
        $parentChildSet = []; // parentId => [childId => true, ...]
        foreach ($relationships as $rel) {
            if ($rel->type === 'parent') {
                $parentChildSet[$rel->personBId][$rel->personAId] = true;
            } elseif ($rel->type === 'child') {
                $parentChildSet[$rel->personAId][$rel->personBId] = true;
            }
        }
        $parentToChildren = [];
        foreach ($parentChildSet as $parentId => $children) {
            $parentToChildren[$parentId] = array_keys($children);
        }

        // Build gender lookup for proper HUSB/WIFE assignment
        $genderById = [];
        foreach ($persons as $p) {
            $genderById[$p->id] = $p->gender ?? 'unknown';
        }

        // Dedupe spouse pairs (forward + inverse stored by RelationshipService)
        $seenSpousePairs = [];
        foreach ($spouseRels as $spouseRel) {
            $a = $spouseRel->personAId;
            $b = $spouseRel->personBId;
            $key = $a < $b ? "$a|$b" : "$b|$a";
            if (isset($seenSpousePairs[$key])) {
                continue;
            }
            $seenSpousePairs[$key] = true;

            // Assign HUSB/WIFE by gender: male → husb, female → wife, unknown → a/b order
            $aId    = $a;
            $bId    = $b;
            $aGender = $genderById[$aId] ?? 'unknown';
            $bGender = $genderById[$bId] ?? 'unknown';

            if ($aGender === 'female' && $bGender !== 'female') {
                [$aId, $bId] = [$bId, $aId]; // swap so male is husb
            }

            $family = [
                'husb'       => $aId,
                'wife'       => $bId,
                'children'   => [],
                'start_date' => $spouseRel->startDate ?? null,
            ];

            // Children = persons who have both partners as parents
            $childrenOfA = $parentToChildren[$a] ?? [];
            $childrenOfB = $parentToChildren[$b] ?? [];
            $family['children'] = array_values(array_intersect($childrenOfA, $childrenOfB));

            // If one side has no children listed, try children of either parent
            if (empty($family['children'])) {
                $family['children'] = array_values(array_unique(
                    array_merge($childrenOfA, $childrenOfB)
                ));
            }

            $parts[]  = $this->buildFam($family, $famIndex, $persons);
            $famIndex++;
        }

        $parts[] = "0 TRLR\n";

        return implode('', $parts);
    }

    /** Build GEDCOM header */
    private function buildHeader(): string
    {
        $date = strtoupper(date('d M Y'));
        return implode("\n", [
            '0 HEAD',
            '1 SOUR Genealog',
            '2 VERS 1.0',
            '1 GEDC',
            '2 VERS 5.5.1',
            '2 FORM LINEAGE-LINKED',
            '1 CHAR UTF-8',
            "1 DATE {$date}",
        ]) . "\n";
    }

    /** Build one INDI record */
    private function buildIndi(\App\Models\Person $person): string
    {
        $xref = $person->gedcomXref ?? ('@I' . $person->id . '@');
        $sex  = match ($person->gender) {
            'male'   => 'M',
            'female' => 'F',
            default  => 'U',
        };

        $lines = [];
        $lines[] = "0 {$xref} INDI";
        $lines[] = "1 NAME {$person->firstName} /{$person->lastName}/";
        $lines[] = "2 GIVN {$person->firstName}";
        $lines[] = "2 SURN {$person->lastName}";

        // RODO Art. 25 — żyjące osoby: minimum danych w eksporcie (tylko imię/nazwisko + rok ur).
        // Maiden name, miejsce urodzenia/zgonu, data zgonu, NOTES — pomijane.
        // Dla zmarłych — pełne dane bez ograniczeń.
        if (!$person->isLiving && !empty($person->maidenName)) {
            $lines[] = "2 _MARN {$person->maidenName}";
        }

        $lines[] = "1 SEX {$sex}";

        // BIRT — dla żyjących TYLKO rok, bez miejsca
        if ($person->birthDate || $person->birthPlace) {
            $lines[] = '1 BIRT';
            if ($person->birthDate) {
                $dateOut = $person->isLiving
                    ? substr($person->birthDate, 0, 4)            // tylko rok
                    : $this->formatGedcomDate($person->birthDate); // pełna data
                $lines[] = '2 DATE ' . $dateOut;
            }
            if ($person->birthPlace && !$person->isLiving) {
                $lines[] = "2 PLAC {$person->birthPlace}";
            }
        }

        // DEAT — pełne dane (zmarłe osoby nie są chronione przez RODO Art. 2(2)(b))
        if (!$person->isLiving) {
            $lines[] = '1 DEAT Y';
            if ($person->deathDate) {
                $lines[] = '2 DATE ' . $this->formatGedcomDate($person->deathDate);
            }
            if ($person->deathPlace) {
                $lines[] = "2 PLAC {$person->deathPlace}";
            }
        }

        // NOTE — pomijane dla żyjących osób (RODO Art. 25)
        if (!empty($person->notes) && !$person->isLiving) {
            $noteLines = explode("\n", $person->notes);
            $first     = true;
            foreach ($noteLines as $noteLine) {
                // Chunk the line
                $chunks = $this->chunkString($noteLine, 248);
                foreach ($chunks as $i => $chunk) {
                    if ($first) {
                        $lines[] = "1 NOTE {$chunk}";
                        $first   = false;
                    } elseif ($i === 0) {
                        $lines[] = "2 CONT {$chunk}";
                    } else {
                        $lines[] = "2 CONC {$chunk}";
                    }
                }
            }
        }

        return implode("\n", $lines) . "\n";
    }

    /** Build one FAM record */
    private function buildFam(array $family, int $famIndex, array $persons): string
    {
        // Build xref lookup
        $xrefById = [];
        foreach ($persons as $p) {
            $xrefById[$p->id] = $p->gedcomXref ?? ('@I' . $p->id . '@');
        }

        $lines   = [];
        $lines[] = "0 @F{$famIndex}@ FAM";

        if ($family['husb']) {
            $xref    = $xrefById[$family['husb']] ?? ('@I' . $family['husb'] . '@');
            $lines[] = "1 HUSB {$xref}";
        }
        if ($family['wife']) {
            $xref    = $xrefById[$family['wife']] ?? ('@I' . $family['wife'] . '@');
            $lines[] = "1 WIFE {$xref}";
        }

        foreach ($family['children'] as $childId) {
            $xref    = $xrefById[$childId] ?? ('@I' . $childId . '@');
            $lines[] = "1 CHIL {$xref}";
        }

        // GEDCOM 5.5.1: emit `1 MARR` for every spouse pair to mark the marriage fact,
        // even when the wedding date is unknown — Ancestry/MyHeritage rely on this tag.
        $lines[] = '1 MARR';
        if ($family['start_date']) {
            $lines[] = '2 DATE ' . $this->formatGedcomDate($family['start_date']);
        }

        return implode("\n", $lines) . "\n";
    }

    // -------------------------------------------------------------------------
    // DATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Parse GEDCOM date string → Y-m-d or null.
     * Handles: "15 MAR 1850", "MAR 1850", "1850", "ABT 1850", "BEF 1850", "AFT 1850", "BET ... AND ..."
     */
    private function parseGedcomDate(string $gedcomDate): ?string
    {
        $date = strtoupper(trim($gedcomDate));

        // Remove approximation prefixes
        $date = preg_replace('/^(ABT|CAL|EST|BEF|AFT|FROM|TO)\s+/', '', $date);

        // BET ... AND ... — strip " AND ..." and parse first date
        if (preg_match('/^BET\s+(.+?)\s+AND\s+.+$/i', $date, $m)) {
            return $this->parseGedcomDate($m[1]);
        }

        // DD MON YYYY
        if (preg_match('/^(\d{1,2})\s+([A-Z]{3})\s+(\d{4})$/', $date, $m)) {
            $month = self::MONTHS[$m[2]] ?? '01';
            return $m[3] . '-' . $month . '-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
        }

        // MON YYYY
        if (preg_match('/^([A-Z]{3})\s+(\d{4})$/', $date, $m)) {
            $month = self::MONTHS[$m[1]] ?? '01';
            return $m[2] . '-' . $month . '-01';
        }

        // YYYY
        if (preg_match('/^(\d{4})$/', $date, $m)) {
            return $m[1] . '-01-01';
        }

        return null;
    }

    /**
     * Format Y-m-d → "DD MON YYYY" for GEDCOM export.
     */
    private function formatGedcomDate(?string $date): string
    {
        if (!$date) {
            return '';
        }
        $parts = explode('-', $date);

        $year  = $parts[0] ?? '';
        $month = isset($parts[1]) ? (int)$parts[1] : 0;
        $day   = isset($parts[2]) ? (int)$parts[2] : 0;

        $monthNames = [
            1 => 'JAN', 2 => 'FEB', 3 => 'MAR', 4 => 'APR',
            5 => 'MAY', 6 => 'JUN', 7 => 'JUL', 8 => 'AUG',
            9 => 'SEP', 10 => 'OCT', 11 => 'NOV', 12 => 'DEC',
        ];

        if ($day > 0 && $month > 0) {
            return sprintf('%02d %s %s', $day, $monthNames[$month] ?? 'JAN', $year);
        }
        if ($month > 0) {
            return ($monthNames[$month] ?? 'JAN') . ' ' . $year;
        }
        return $year;
    }

    // -------------------------------------------------------------------------
    // HELPERS
    // -------------------------------------------------------------------------

    /** Normalize xref: ensure "@XREF@" format */
    private function normalizeXref(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (!str_starts_with($raw, '@')) {
            $raw = '@' . $raw . '@';
        }
        return $raw;
    }

    /** Split string into chunks of max $len characters (UTF-8 safe) */
    private function chunkString(string $str, int $len): array
    {
        if ($str === '') {
            return [''];
        }
        $chars  = mb_str_split($str, $len, 'UTF-8');
        return $chars ?: [$str];
    }

}
