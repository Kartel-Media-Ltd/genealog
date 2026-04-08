<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\CrossTreeLink;

class CrossTreeLinkRepository
{
    public function __construct(private readonly Database $db) {}

    // -------------------------------------------------------------------------
    // Zapis / aktualizacja
    // -------------------------------------------------------------------------

    public function create(
        string  $id,
        string  $requesterPersonId,
        string  $targetPersonId,
        string  $requesterUserId,
        string  $targetUserId,
        string  $visibilityLevel = CrossTreeLink::VISIBILITY_BASIC,
        ?string $note = null,
    ): void {
        $pairKey = self::buildPairKey($requesterPersonId, $targetPersonId);
        $this->db->execute(
            'INSERT INTO cross_tree_links
             (id, requester_person_id, target_person_id, requester_user_id, target_user_id, visibility_level, note, pair_key)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$id, $requesterPersonId, $targetPersonId, $requesterUserId, $targetUserId, $visibilityLevel, $note, $pairKey]
        );
    }

    /** Buduje kanoniczny klucz pary: mniejszy ID | większy ID */
    public static function buildPairKey(string $a, string $b): string
    {
        return ($a < $b ? $a : $b) . '|' . ($a < $b ? $b : $a);
    }

    /**
     * Atomowa aktualizacja statusu tylko gdy obecny status pasuje.
     * Chroni przed race conditions (optimistic locking).
     */
    public function updateStatusIfEquals(string $id, string $currentStatus, string $newStatus): bool
    {
        $affected = $this->db->execute(
            'UPDATE cross_tree_links SET status = ? WHERE id = ? AND status = ?',
            [$newStatus, $id, $currentStatus]
        );
        return $affected > 0;
    }

    /** Ustaw broken dla wszystkich połączeń zawierających daną osobę */
    public function markBrokenByPersonId(string $personId): void
    {
        $this->db->execute(
            "UPDATE cross_tree_links
             SET status = 'broken'
             WHERE (requester_person_id = ? OR target_person_id = ?)
               AND status IN ('pending','accepted')",
            [$personId, $personId]
        );
    }

    /** Ustaw broken dla wszystkich połączeń drzewa (gdy drzewo zostaje odłączone od discovery) */
    public function markBrokenByTreeId(string $treeId): void
    {
        $this->db->execute(
            "UPDATE cross_tree_links ctl
             INNER JOIN persons p
               ON p.id IN (ctl.requester_person_id, ctl.target_person_id)
             SET ctl.status = 'broken'
             WHERE p.tree_id = ?
               AND ctl.status IN ('pending','accepted')",
            [$treeId]
        );
    }

    // -------------------------------------------------------------------------
    // Odczyt
    // -------------------------------------------------------------------------

    public function findById(string $id): ?CrossTreeLink
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM cross_tree_links WHERE id = ?',
            [$id]
        );
        return $row ? CrossTreeLink::fromArray($row) : null;
    }

    /**
     * Zwraca mapę gpiId → status dla wszystkich wychodzących próśb danej osoby.
     * Używane do wyświetlania statusu per-wynik w panelu discovery.
     *
     * @return array<string, array{status: string, note: string|null, visibilityLevel: string}>
     *         klucz = global_person_index.id
     */
    public function findOutgoingStatusByGpiId(string $requesterPersonId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT ctl.status, ctl.note, ctl.visibility_level, gpi.id AS gpi_id
             FROM cross_tree_links ctl
             JOIN global_person_index gpi ON gpi.person_id = ctl.target_person_id
             WHERE ctl.requester_person_id = ?
               AND ctl.status NOT IN ('broken')",
            [$requesterPersonId]
        );
        $map = [];
        foreach ($rows as $row) {
            $map[$row['gpi_id']] = [
                'status'          => $row['status'],
                'note'            => $row['note'],
                'visibilityLevel' => $row['visibility_level'],
            ];
        }
        return $map;
    }

    /** Usuwa połączenie z bazy (admin override — np. po rejected/cancelled). */
    public function deleteById(string $id): void
    {
        $this->db->execute('DELETE FROM cross_tree_links WHERE id = ?', [$id]);
    }

    /**
     * Sprawdza czy dana osoba ma aktywną (pending) wychodzącą prośbę cross-tree.
     * Używane do blokowania wysyłania kolejnych próśb gdy jedna już oczekuje.
     */
    public function hasPendingOutgoingForPerson(string $personId): bool
    {
        $row = $this->db->fetchOne(
            "SELECT 1 FROM cross_tree_links
             WHERE requester_person_id = ? AND status = 'pending' LIMIT 1",
            [$personId]
        );
        return (bool)$row;
    }

    /**
     * Znajdź istniejące połączenie między dwiema osobami (bez względu na kierunek).
     * Używa generowanej kolumny pair_key dla wydajnego wyszukiwania.
     */
    public function findByPersonPair(string $personIdA, string $personIdB): ?CrossTreeLink
    {
        $pairKey = self::buildPairKey($personIdA, $personIdB);

        $row = $this->db->fetchOne(
            'SELECT * FROM cross_tree_links WHERE pair_key = ? LIMIT 1',
            [$pairKey]
        );
        return $row ? CrossTreeLink::fromArray($row) : null;
    }

    /**
     * Oczekujące prośby skierowane DO danego użytkownika.
     * Dołącza dane obu osób i drzew (uproszczony widok do wyświetlania).
     *
     * @return array[]
     */
    public function findPendingForTargetUser(string $targetUserId): array
    {
        return $this->db->fetchAll(
            "SELECT ctl.*,
                    rp.first_name  AS req_first_name,  rp.last_name  AS req_last_name,
                    rp.birth_date  AS req_birth_date,  rp.birth_place AS req_birth_place,
                    rp.death_date  AS req_death_date,  rp.gender      AS req_gender,
                    rt.name        AS req_tree_name,   rt.id          AS req_tree_id,
                    ru.name        AS requester_name,
                    tp.first_name  AS tgt_first_name,  tp.last_name   AS tgt_last_name,
                    tp.birth_date  AS tgt_birth_date,  tp.birth_place AS tgt_birth_place,
                    tp.death_date  AS tgt_death_date,  tp.tree_id     AS tgt_tree_id
             FROM cross_tree_links ctl
             JOIN persons rp ON rp.id = ctl.requester_person_id
             JOIN trees   rt ON rt.id = rp.tree_id
             JOIN users   ru ON ru.id = ctl.requester_user_id
             JOIN persons tp ON tp.id = ctl.target_person_id
             WHERE ctl.target_user_id = ? AND ctl.status = 'pending'
             ORDER BY ctl.created_at DESC",
            [$targetUserId]
        );
    }

    /**
     * Prośby wysłane przez danego użytkownika (oczekujące + odrzucone + anulowane).
     *
     * @return array[]
     */
    public function findSentByUser(string $requesterUserId): array
    {
        return $this->db->fetchAll(
            "SELECT ctl.*,
                    rp.first_name AS req_first_name, rp.last_name AS req_last_name,
                    rp.tree_id    AS req_tree_id,
                    rt.name       AS req_tree_name,
                    tp.first_name AS tgt_first_name, tp.last_name AS tgt_last_name,
                    tp.birth_date AS tgt_birth_date, tp.tree_id   AS tgt_tree_id,
                    tt.name       AS tgt_tree_name,
                    tu.name       AS target_user_name
             FROM cross_tree_links ctl
             JOIN persons rp ON rp.id = ctl.requester_person_id
             JOIN trees   rt ON rt.id = rp.tree_id
             JOIN persons tp ON tp.id = ctl.target_person_id
             JOIN trees   tt ON tt.id = tp.tree_id
             JOIN users   tu ON tu.id = ctl.target_user_id
             WHERE ctl.requester_user_id = ?
               AND ctl.status IN ('pending','rejected','cancelled')
             ORDER BY ctl.created_at DESC",
            [$requesterUserId]
        );
    }

    /**
     * Zaakceptowane połączenia danego użytkownika (z obu stron).
     *
     * @return array[]
     */
    public function findAcceptedForUser(string $userId): array
    {
        return $this->db->fetchAll(
            "SELECT ctl.*,
                    rp.first_name AS req_first_name, rp.last_name AS req_last_name,
                    rp.tree_id    AS req_tree_id,   rt.name AS req_tree_name,
                    tp.first_name AS tgt_first_name, tp.last_name AS tgt_last_name,
                    tp.tree_id    AS tgt_tree_id,   tt.name AS tgt_tree_name,
                    ru.name AS requester_name, tu.name AS target_user_name
             FROM cross_tree_links ctl
             JOIN persons rp ON rp.id = ctl.requester_person_id
             JOIN trees   rt ON rt.id = rp.tree_id
             JOIN persons tp ON tp.id = ctl.target_person_id
             JOIN trees   tt ON tt.id = tp.tree_id
             JOIN users   ru ON ru.id = ctl.requester_user_id
             JOIN users   tu ON tu.id = ctl.target_user_id
             WHERE (ctl.requester_user_id = ? OR ctl.target_user_id = ?)
               AND ctl.status = 'accepted'
             ORDER BY ctl.updated_at DESC",
            [$userId, $userId]
        );
    }

    /**
     * Zaakceptowane połączenia dla konkretnej osoby — do renderowania drzewa.
     *
     * @return array[]
     */
    public function findAcceptedForPerson(string $personId): array
    {
        return $this->db->fetchAll(
            "SELECT * FROM v_active_cross_tree_links WHERE person_a_id = ?",
            [$personId]
        );
    }

    /**
     * ID zdalnych drzew połączonych (zaakceptowanych) z danym drzewem.
     * Używane do buildowania merged view w D3.js.
     *
     * @return string[]
     */
    public function findLinkedTreeIdsForTree(string $treeId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT DISTINCT
                CASE
                    WHEN rp.tree_id = ? THEN tp.tree_id
                    ELSE rp.tree_id
                END AS linked_tree_id
             FROM cross_tree_links ctl
             JOIN persons rp ON rp.id = ctl.requester_person_id
             JOIN persons tp ON tp.id = ctl.target_person_id
             WHERE (rp.tree_id = ? OR tp.tree_id = ?)
               AND ctl.status = 'accepted'",
            [$treeId, $treeId, $treeId]
        );
        return array_column($rows, 'linked_tree_id');
    }

    /**
     * Pary połączonych person_id do rysowania gold dashed edges w D3.js.
     * Zwraca wszystkie zaakceptowane pary gdzie jedno z drzew należy do $treeId.
     *
     * @return array[] każdy element: ['person_a_id', 'person_b_id', 'visibility_level']
     */
    public function findAcceptedPairsForTree(string $treeId): array
    {
        return $this->db->fetchAll(
            "SELECT ctl.id,
                    ctl.requester_person_id AS person_a_id,
                    ctl.target_person_id    AS person_b_id,
                    ctl.visibility_level
             FROM cross_tree_links ctl
             JOIN persons rp ON rp.id = ctl.requester_person_id
             JOIN persons tp ON tp.id = ctl.target_person_id
             WHERE (rp.tree_id = ? OR tp.tree_id = ?)
               AND ctl.status = 'accepted'",
            [$treeId, $treeId]
        );
    }
}
