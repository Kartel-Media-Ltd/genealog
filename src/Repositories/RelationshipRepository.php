<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Relationship;

class RelationshipRepository
{
    public function __construct(private readonly Database $db) {}

    /** @return Relationship[] — relationships for a person with joined related-person data */
    public function findByPerson(string $personId, string $treeId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT r.*,
                    p.first_name AS related_first_name,
                    p.last_name  AS related_last_name,
                    p.photo_path AS related_photo_path,
                    p.gender     AS related_gender,
                    p.birth_date AS related_birth_date
             FROM relationships r
             JOIN persons p ON p.id = r.person_b_id AND p.tree_id = r.tree_id
             WHERE r.person_a_id = ? AND r.tree_id = ?
             ORDER BY r.type, p.last_name",
            [$personId, $treeId]
        );

        return array_map(fn($r) => Relationship::fromArray($r), $rows);
    }

    /** @return Relationship[] — all relationships in tree (for D3 API) */
    public function findByTree(string $treeId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT * FROM relationships WHERE tree_id = ? ORDER BY type',
            [$treeId]
        );
        return array_map(fn($r) => Relationship::fromArray($r), $rows);
    }

    public function create(
        string  $id,
        string  $treeId,
        string  $personAId,
        string  $personBId,
        string  $type,
        ?string $startDate = null,
        ?string $endDate   = null,
        ?string $notes     = null,
    ): void {
        $this->db->execute(
            'INSERT INTO relationships
             (id, tree_id, person_a_id, person_b_id, type, start_date, end_date, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
            [$id, $treeId, $personAId, $personBId, $type, $startDate, $endDate, $notes]
        );
    }

    public function delete(string $id, string $treeId): void
    {
        $this->db->execute(
            'DELETE FROM relationships WHERE id = ? AND tree_id = ?',
            [$id, $treeId]
        );
    }

    public function exists(string $personAId, string $personBId, string $type, string $treeId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT 1 FROM relationships
             WHERE person_a_id = ? AND person_b_id = ? AND type = ? AND tree_id = ?',
            [$personAId, $personBId, $type, $treeId]
        );
        return $row !== false && $row !== null;
    }

    public function findById(string $id, string $treeId): ?Relationship
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM relationships WHERE id = ? AND tree_id = ?',
            [$id, $treeId]
        );
        return $row ? Relationship::fromArray($row) : null;
    }

    public function findByPersonAndTarget(string $personAId, string $personBId, string $type, string $treeId): ?Relationship
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM relationships WHERE person_a_id = ? AND person_b_id = ? AND type = ? AND tree_id = ?',
            [$personAId, $personBId, $type, $treeId]
        );
        return $row ? Relationship::fromArray($row) : null;
    }
}
