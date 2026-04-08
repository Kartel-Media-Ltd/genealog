<?php
declare(strict_types=1);

namespace App\Services\Discovery;

use App\Core\Database;
use App\Repositories\DiscoveryRepository;
use App\Services\PersonService;

/**
 * Importuje dane z `person_match_suggestions` do drzewa jako nowa osoba
 * (lub — w przyszłości — uzupełnia istniejącą, co wymaga przepływu merge UI).
 *
 * Bezpieczeństwo:
 *   - Optimistic lock: UPDATE ... WHERE status='pending' (audyt P3)
 *   - IDOR check: DiscoveryController::importMatch weryfikuje created_for_user
 *   - Audit log: każdy import trafia do source_audit_log
 */
final class PersonImportService
{
    public function __construct(
        private readonly Database            $db,
        private readonly DiscoveryRepository $repo,
        private readonly PersonService       $personService,
    ) {}

    /**
     * Tworzy nową osobę w drzewie na podstawie propozycji dopasowania.
     * Używa optimistic lock — jeśli inny request zdążył już zaimportować,
     * rzuca RuntimeException (caller powinien zwrócić informacyjny błąd).
     *
     * @param array<string, mixed> $suggestion rekord z person_match_suggestions
     * @return string ID nowo utworzonej osoby
     */
    public function importFromMatch(
        array  $suggestion,
        string $treeId,
        string $userId,
        ?string $ip = null,
    ): string {
        $sourceData = is_string($suggestion['source_data'])
            ? (json_decode((string)$suggestion['source_data'], true) ?? [])
            : (array)($suggestion['source_data'] ?? []);

        // B4 fix: dla cross-tree match `birthPlace` jest pomijane przez RODO
        // (anonimizacja w MatchResult::jsonSerialize), ale `region` (województwo)
        // zostaje. Mapujemy region → birth_place gdy brak konkretnego miejsca,
        // żeby nie tracić informacji geograficznej.
        $birthPlace = (string)($sourceData['birthPlace'] ?? '');
        if ($birthPlace === '' && !empty($sourceData['region'])) {
            $birthPlace = (string)$sourceData['region'];
        }

        // is_living: dla local match preferujemy wartość ze źródła (kopiowanie
        // osoby z innego drzewa tego samego usera). Dla cross-tree i external
        // zawsze 0 — wszystkie te osoby są historyczne (RODO Art. 25).
        $sourceType = (string)($suggestion['source_type'] ?? '');
        $isLiving   = $sourceType === 'local'
            ? (int)($sourceData['isLiving'] ?? 0)
            : 0;

        // Mapa pól z MatchResult JSON → input do PersonService::create
        $input = [
            'first_name'  => (string)($sourceData['firstName']  ?? ''),
            'last_name'   => (string)($sourceData['lastName']   ?? ''),
            'birth_date'  => !empty($sourceData['birthYear']) ? (string)$sourceData['birthYear'] : '',
            'birth_place' => $birthPlace,
            'death_date'  => !empty($sourceData['deathYear']) ? (string)$sourceData['deathYear'] : '',
            'gender'      => (string)($sourceData['gender']     ?? 'unknown'),
            'is_living'   => $isLiving,
            'visibility'  => 'private',
        ];

        // Minimum walidacja — PersonService::create też sprawdzi
        if ($input['first_name'] === '' || $input['last_name'] === '') {
            throw new \InvalidArgumentException('Brakuje imienia lub nazwiska w propozycji.');
        }

        // B5: całość w transakcji — status suggestion + utworzenie osoby + audit
        // muszą być atomowe żeby nie zostawić sugestii `imported` bez istniejącej osoby.
        $this->db->beginTransaction();
        try {
            // Optimistic lock — UPDATE ... WHERE status='pending'
            $affected = $this->repo->updateStatusIfPending((string)$suggestion['id'], 'imported');
            if ($affected === 0) {
                $this->db->rollback();
                throw new \RuntimeException('Ta propozycja została już zaakceptowana przez kogoś innego.');
            }

            $person = $this->personService->create($treeId, $userId, $input);

            $this->repo->logAudit(
                userId:         $userId,
                action:         'import',
                sourceType:     $sourceType,
                sourceId:       (string)$suggestion['source_id'],
                targetPersonId: $person->id,
                targetTreeId:   $treeId,
                ip:             $ip,
            );

            $this->db->commit();
            return $person->id;
        } catch (\Throwable $e) {
            // B5 fix: zawsze sprawdzamy inTransaction() przed rollback (ujednolicony catch)
            // Stara wersja: catch RuntimeException re-throwowała bez rollback gdy person->create rzuciło RuntimeException
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }
}
