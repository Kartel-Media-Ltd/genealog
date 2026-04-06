<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Tree;

class TreeRepository
{
    public function __construct(private readonly Database $db) {}

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
}
