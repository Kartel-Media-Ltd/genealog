<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class NotificationRepository
{
    public function __construct(private readonly Database $db) {}

    public function create(
        string  $id,
        string  $userId,
        string  $type,
        string  $title,
        ?string $body = null,
        ?string $link = null,
    ): void {
        $this->db->execute(
            'INSERT INTO notifications (id, user_id, type, title, body, link)
             VALUES (?, ?, ?, ?, ?, ?)',
            [$id, $userId, $type, $title, $body, $link]
        );
    }

    /** @return array[] */
    public function findRecent(string $userId, int $limit = 10): array
    {
        $lim = max(1, min(50, $limit));
        return $this->db->fetchAll(
            "SELECT * FROM notifications
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT {$lim}",
            [$userId]
        );
    }

    public function countUnread(string $userId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM notifications
             WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
        return (int)($row['cnt'] ?? 0);
    }

    public function markAsRead(string $id, string $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }

    public function markAllAsRead(string $userId): void
    {
        $this->db->execute(
            'UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE user_id = ? AND is_read = 0',
            [$userId]
        );
    }

    public function findById(string $id, string $userId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM notifications WHERE id = ? AND user_id = ?',
            [$id, $userId]
        );
    }
}
