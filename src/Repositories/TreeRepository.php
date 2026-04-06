<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class TreeRepository
{
    public function __construct(private readonly Database $db) {}

    public function findByOwner(string $userId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM trees WHERE owner_id = :owner_id ORDER BY created_at DESC',
            [':owner_id' => $userId]
        );
    }
}
