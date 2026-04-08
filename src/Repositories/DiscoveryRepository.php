<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Core\Uuid;

/**
 * CRUD dla `person_match_suggestions` i `source_audit_log`.
 * Tabele są utworzone przez migracje 008_discovery.sql.
 */
final class DiscoveryRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * Zapisuje propozycję dopasowania z deduplikacją (UNIQUE na personId+sourceType+sourceId).
     * Jeśli istnieje → zwraca istniejące id. Jeśli nie → tworzy nowe.
     */
    public function saveSuggestion(
        string $personId,
        string $sourceType,
        string $sourceId,
        array  $sourceData,
        float  $confidence,
        string $forUserId,
    ): string {
        $existing = $this->db->fetchOne(
            'SELECT id FROM person_match_suggestions
             WHERE person_id = ? AND source_type = ? AND source_id = ?',
            [$personId, $sourceType, $sourceId]
        );
        if ($existing) {
            return (string)$existing['id'];
        }

        $id = Uuid::generate();
        $this->db->execute(
            'INSERT INTO person_match_suggestions
             (id, person_id, source_type, source_id, source_data, confidence, status, created_for_user, created_at)
             VALUES (?, ?, ?, ?, ?, ?, \'pending\', ?, NOW())',
            [
                $id, $personId, $sourceType, $sourceId,
                json_encode($sourceData, JSON_UNESCAPED_UNICODE),
                $confidence, $forUserId,
            ]
        );
        return $id;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findPendingForPerson(string $personId, string $forUserId): array
    {
        return $this->db->fetchAll(
            'SELECT id, person_id, source_type, source_id, source_data, confidence, status, created_at
             FROM person_match_suggestions
             WHERE person_id = ? AND created_for_user = ? AND status = \'pending\'
             ORDER BY confidence DESC, created_at DESC
             LIMIT 20',
            [$personId, $forUserId]
        );
    }

    public function findById(string $id, string $forUserId): ?array
    {
        $row = $this->db->fetchOne(
            'SELECT id, person_id, source_type, source_id, source_data, confidence, status, created_for_user
             FROM person_match_suggestions
             WHERE id = ? AND created_for_user = ?',
            [$id, $forUserId]
        );
        return $row ?: null;
    }

    /**
     * Optimistic lock — zwraca liczbę zmienionych wierszy.
     * Używane w PersonImportService::importFromMatch (race condition prevention).
     */
    public function updateStatusIfPending(string $id, string $newStatus): int
    {
        return $this->db->execute(
            'UPDATE person_match_suggestions
             SET status = ?
             WHERE id = ? AND status = \'pending\'',
            [$newStatus, $id]
        );
    }

    /**
     * Zwraca person_id z global_person_index po GPI record ID.
     * Używane do budowania cross-tree link request z live search result (gdzie sourceId = gpi.id).
     */
    public function findPersonIdByGpiId(string $gpiId): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT person_id FROM global_person_index WHERE id = ?',
            [$gpiId]
        );
        return $row ? (string)$row['person_id'] : null;
    }

    public function logAudit(
        string  $userId,
        string  $action,
        string  $sourceType,
        ?string $sourceId,
        ?string $targetPersonId,
        ?string $targetTreeId,
        ?string $ip,
    ): void {
        $this->db->execute(
            'INSERT INTO source_audit_log
             (id, user_id, action, source_type, source_id, target_person_id, target_tree_id, ip, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                Uuid::generate(), $userId, $action, $sourceType,
                $sourceId, $targetPersonId, $targetTreeId, $ip,
            ]
        );
    }

}
