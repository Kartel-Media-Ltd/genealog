<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Tree;

class TreeRepository
{
    public function __construct(private readonly Database $db) {}

    /**
     * Zwraca listę ID wszystkich drzew do których user ma dostęp
     * (owner LUB member przez tree_members). Używane np. do cross-tree search.
     *
     * @return list<string>
     */
    public function findAccessibleIdsForUser(string $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT t.id
             FROM trees t
             LEFT JOIN tree_members tm ON tm.tree_id = t.id AND tm.user_id = :uid
             WHERE t.owner_id = :uid2 OR tm.user_id IS NOT NULL',
            [':uid' => $userId, ':uid2' => $userId]
        );
        return array_map(static fn(array $r): string => (string)$r['id'], $rows);
    }

    /**
     * Zwraca drzewa których właścicielem jest user.
     * @return Tree[]
     */
    public function findByOwner(string $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.*, COUNT(p.id) AS persons_count
             FROM trees t
             LEFT JOIN persons p ON p.tree_id = t.id
             WHERE t.owner_id = :uid
             GROUP BY t.id
             ORDER BY t.updated_at DESC',
            [':uid' => $userId]
        );

        return array_map(Tree::fromArray(...), $rows);
    }

    /**
     * Drzewo widoczne dla użytkownika (właściciel lub member).
     */
    public function findForUser(string $treeId, string $userId): ?Tree
    {
        $row = $this->db->fetchOne(
            'SELECT t.*, COUNT(p.id) AS persons_count
             FROM trees t
             LEFT JOIN persons p ON p.tree_id = t.id
             WHERE t.id = :tid
               AND (t.owner_id = :uid
                    OR EXISTS (SELECT 1 FROM tree_members tm
                               WHERE tm.tree_id = t.id AND tm.user_id = :uid2))
             GROUP BY t.id',
            [':tid' => $treeId, ':uid' => $userId, ':uid2' => $userId]
        );

        return $row ? Tree::fromArray($row) : null;
    }

    public function findById(string $id): ?Tree
    {
        $row = $this->db->fetchOne(
            'SELECT t.*, COUNT(p.id) AS persons_count
             FROM trees t
             LEFT JOIN persons p ON p.tree_id = t.id
             WHERE t.id = :id
             GROUP BY t.id',
            [':id' => $id]
        );

        return $row ? Tree::fromArray($row) : null;
    }

    public function create(string $id, string $ownerId, string $name, ?string $description, bool $isPublic): void
    {
        $this->db->execute(
            'INSERT INTO trees (id, owner_id, name, description, is_public)
             VALUES (:id, :owner_id, :name, :description, :is_public)',
            [
                ':id'          => $id,
                ':owner_id'    => $ownerId,
                ':name'        => $name,
                ':description' => $description,
                ':is_public'   => (int)$isPublic,
            ]
        );

        // Właściciel automatycznie staje się memberem z rolą owner
        $this->db->execute(
            'INSERT INTO tree_members (tree_id, user_id, role) VALUES (:tid, :uid, :role)',
            [':tid' => $id, ':uid' => $ownerId, ':role' => 'owner']
        );
    }

    public function update(string $id, string $name, ?string $description, bool $isPublic): void
    {
        $this->db->execute(
            'UPDATE trees SET name = :name, description = :description, is_public = :is_public
             WHERE id = :id',
            [
                ':id'          => $id,
                ':name'        => $name,
                ':description' => $description,
                ':is_public'   => (int)$isPublic,
            ]
        );
    }

    /**
     * Drzewa, do których user jest zaproszony (nie właściciel).
     * @return Tree[]
     */
    public function findByMember(string $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.*, COUNT(p.id) AS persons_count,
                    u.name AS owner_name
             FROM trees t
             JOIN tree_members tm ON tm.tree_id = t.id AND tm.user_id = :uid AND tm.role != :role
             JOIN users u ON u.id = t.owner_id
             LEFT JOIN persons p ON p.tree_id = t.id
             WHERE t.owner_id != :uid2
             GROUP BY t.id
             ORDER BY t.updated_at DESC',
            [':uid' => $userId, ':uid2' => $userId, ':role' => 'owner']
        );

        return array_map(Tree::fromArray(...), $rows);
    }

    public function getUserRole(string $treeId, string $userId): ?string
    {
        $row = $this->db->fetchOne(
            'SELECT role FROM tree_members WHERE tree_id = :tid AND user_id = :uid',
            [':tid' => $treeId, ':uid' => $userId]
        );

        return $row['role'] ?? null;
    }

    public function isOwner(string $treeId, string $userId): bool
    {
        $row = $this->db->fetchOne(
            'SELECT 1 FROM trees WHERE id = :id AND owner_id = :uid',
            [':id' => $treeId, ':uid' => $userId]
        );

        return $row !== null;
    }

    public function touchUpdatedAt(string $treeId): void
    {
        $this->db->execute(
            'UPDATE trees SET updated_at = NOW() WHERE id = ?',
            [$treeId]
        );
    }

    /**
     * Ostatnio dodane osoby we wszystkich drzewach usera (dla sekcji "Ostatnia aktywność").
     * @return array{description: string, created_at: string}[]
     */
    public function getRecentPersonActivity(string $userId, int $limit = 10): array
    {
        // safe: $lim jest int-castowany przez max(1, (int)$limit) — PDO nie wspiera bound LIMIT params
        $lim  = max(1, (int)$limit);
        $rows = $this->db->fetchAll(
            "SELECT p.first_name, p.last_name, p.created_at, t.name AS tree_name, t.id AS tree_id, p.id AS person_id
             FROM persons p
             JOIN trees t ON t.id = p.tree_id
             WHERE t.owner_id = :uid
                OR EXISTS (SELECT 1 FROM tree_members tm WHERE tm.tree_id = t.id AND tm.user_id = :uid2)
             ORDER BY p.created_at DESC
             LIMIT {$lim}",
            [':uid' => $userId, ':uid2' => $userId]
        );

        return array_map(static function (array $row): array {
            $name = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
            return [
                'description' => 'Dodano osobę: ' . $name . ' (drzewo: ' . $row['tree_name'] . ')',
                'created_at'  => $row['created_at'],
                'link'        => '/trees/' . $row['tree_id'] . '/persons/' . $row['person_id'],
            ];
        }, $rows);
    }
}
