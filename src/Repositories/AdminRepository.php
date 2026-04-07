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
        // Cast to int for safe interpolation (PDO native prepares reject LIMIT with bound string)
        $lim = max(1, $limit);
        $off = max(0, $offset);

        // Escape LIKE wildcards (% and _) so search 'foo_bar' is literal, not pattern
        $escaped = addcslashes($search, '%_\\');
        $like    = '%' . $escaped . '%';

        return $this->db->fetchAll(
            "SELECT u.id, u.name, u.email, u.is_admin, u.is_blocked, u.is_active, u.created_at,
                    COUNT(t.id) AS trees_count
             FROM users u
             LEFT JOIN trees t ON t.owner_id = u.id
             WHERE u.name LIKE ? ESCAPE '\\\\' OR u.email LIKE ? ESCAPE '\\\\'
             GROUP BY u.id
             ORDER BY u.created_at DESC
             LIMIT {$lim} OFFSET {$off}",
            [$like, $like]
        );
    }

    public function countUsers(string $search = ''): int
    {
        $escaped = addcslashes($search, '%_\\');
        $like    = '%' . $escaped . '%';
        $row     = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM users WHERE name LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\'",
            [$like, $like]
        );
        return (int)($row['cnt'] ?? 0);
    }

    /** @return array[] */
    public function findAllTrees(int $limit = 50, int $offset = 0): array
    {
        $lim = max(1, $limit);
        $off = max(0, $offset);

        return $this->db->fetchAll(
            "SELECT t.*, u.name AS owner_name, u.email AS owner_email,
                    COUNT(p.id) AS persons_count
             FROM trees t
             JOIN users u ON u.id = t.owner_id
             LEFT JOIN persons p ON p.tree_id = t.id
             GROUP BY t.id
             ORDER BY t.updated_at DESC
             LIMIT {$lim} OFFSET {$off}"
        );
    }

    public function countTrees(): int
    {
        return (int)($this->db->fetchOne('SELECT COUNT(*) AS cnt FROM trees')['cnt'] ?? 0);
    }

    /**
     * @param array{action?: string, admin_id?: string, target_id?: string, date_from?: string, date_to?: string} $filters
     * @return array[]
     */
    public function findLogs(int $limit = 50, int $offset = 0, array $filters = []): array
    {
        $lim = max(1, $limit);
        $off = max(0, $offset);

        [$where, $params] = $this->buildLogFilters($filters);

        return $this->db->fetchAll(
            "SELECT al.*, u.name AS admin_name, u.email AS admin_email
             FROM admin_logs al
             JOIN users u ON u.id = al.admin_id
             {$where}
             ORDER BY al.created_at DESC
             LIMIT {$lim} OFFSET {$off}",
            $params
        );
    }

    /** @param array<string, string> $filters */
    public function countLogs(array $filters = []): int
    {
        [$where, $params] = $this->buildLogFilters($filters);
        $row = $this->db->fetchOne(
            "SELECT COUNT(*) AS cnt FROM admin_logs al {$where}",
            $params
        );
        return (int)($row['cnt'] ?? 0);
    }

    /** @return list<string> Lista distinct typów akcji w logach (do filter dropdown) */
    public function getLogActions(): array
    {
        $rows = $this->db->fetchAll('SELECT DISTINCT action FROM admin_logs ORDER BY action');
        return array_map(fn(array $r) => (string)$r['action'], $rows);
    }

    /**
     * @param array<string, string> $filters
     * @return array{0: string, 1: array<int, string>}
     */
    private function buildLogFilters(array $filters): array
    {
        $conditions = [];
        $params     = [];

        if (!empty($filters['action'])) {
            $conditions[] = 'al.action = ?';
            $params[]     = $filters['action'];
        }
        if (!empty($filters['admin_id'])) {
            $conditions[] = 'al.admin_id = ?';
            $params[]     = $filters['admin_id'];
        }
        if (!empty($filters['target_id'])) {
            $conditions[] = 'al.target_id = ?';
            $params[]     = $filters['target_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = 'al.created_at >= ?';
            $params[]     = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = 'al.created_at <= ?';
            $params[]     = $filters['date_to'] . ' 23:59:59';
        }

        $where = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);
        return [$where, $params];
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
