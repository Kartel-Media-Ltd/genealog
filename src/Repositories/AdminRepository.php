<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class AdminRepository
{
    public function __construct(private readonly Database $db) {}

    public function getStats(): array
    {
        return [
            'users'         => (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE is_blocked = 0')['cnt'] ?? 0),
            'blocked_users' => (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM users WHERE is_blocked = 1')['cnt'] ?? 0),
            'trees'         => (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM trees')['cnt'] ?? 0),
            'persons'       => (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM persons')['cnt'] ?? 0),
            'relationships' => (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM relationships')['cnt'] ?? 0),
        ];
    }

    /** @return array[] */
    public function findAllUsers(string $search = '', int $limit = 50, int $offset = 0): array
    {
        $like   = '%' . $search . '%';
        $params = [$like, $like, $limit, $offset];

        return $this->db->fetchAll(
            'SELECT u.id, u.name, u.email, u.is_admin, u.is_blocked, u.is_active, u.created_at,
                    COUNT(t.id) AS trees_count
             FROM users u
             LEFT JOIN trees t ON t.owner_id = u.id
             WHERE u.name LIKE ? OR u.email LIKE ?
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT ? OFFSET ?',
            $params
        );
    }

    public function countUsers(string $search = ''): int
    {
        $like = '%' . $search . '%';
        $row  = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM users WHERE name LIKE ? OR email LIKE ?',
            [$like, $like]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /** @return array[] */
    public function findAllTrees(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, u.name AS owner_name, u.email AS owner_email,
                    COUNT(p.id) AS persons_count
             FROM trees t
             JOIN users u ON u.id = t.owner_id
             LEFT JOIN persons p ON p.tree_id = t.id
             GROUP BY t.id
             ORDER BY t.updated_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function countTrees(): int
    {
        return (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM trees')['cnt'] ?? 0);
    }

    /** @return array[] */
    public function findLogs(int $limit = 50, int $offset = 0): array
    {
        return $this->db->fetchAll(
            'SELECT al.*, u.name AS admin_name, u.email AS admin_email
             FROM admin_logs al
             JOIN users u ON u.id = al.admin_id
             ORDER BY al.created_at DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );
    }

    public function countLogs(): int
    {
        return (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM admin_logs')['cnt'] ?? 0);
    }

    public function createLog(
        string  $id,
        string  $adminId,
        string  $action,
        ?string $targetType,
        ?string $targetId,
        ?string $meta,
    ): void {
        $this->db->execute(
            'INSERT INTO admin_logs (id, admin_id, action, target_type, target_id, meta)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $adminId, $action, $targetType, $targetId, $meta]
        );
    }

    /** @return array[] — trees of a specific user */
    public function findUserTrees(string $userId): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, COUNT(p.id) AS persons_count
             FROM trees t
             LEFT JOIN persons p ON p.tree_id = t.id
             WHERE t.owner_id = ?
             GROUP BY t.id
             ORDER BY t.updated_at DESC',
            [$userId]
        );
    }

    public function findUserById(string $id): ?array
    {
        return $this->db->fetchOne(
            'SELECT u.*, COUNT(t.id) AS trees_count
             FROM users u
             LEFT JOIN trees t ON t.owner_id = u.id
             WHERE u.id = ?
             GROUP BY u.id',
            [$id]
        ) ?: null;
    }
}
