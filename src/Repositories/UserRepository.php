<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\User;

class UserRepository
{
    public function __construct(private readonly Database $db) {}

    public function findByEmail(string $email): ?User
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE email = :email AND is_active = 1', [':email' => $email]);
        return $row ? User::fromArray($row) : null;
    }

    public function findById(string $id): ?User
    {
        $row = $this->db->fetchOne('SELECT * FROM users WHERE id = :id AND is_active = 1', [':id' => $id]);
        return $row ? User::fromArray($row) : null;
    }

    public function findByEmailWithHash(string $email): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE email = :email AND is_active = 1', [':email' => $email]);
    }

    public function create(string $id, string $email, string $passwordHash, string $name, string $locale = 'pl'): bool
    {
        $affected = $this->db->execute(
            'INSERT INTO users (id, email, password_hash, name, locale) VALUES (:id, :email, :hash, :name, :locale)',
            [':id' => $id, ':email' => $email, ':hash' => $passwordHash, ':name' => $name, ':locale' => $locale]
        );
        return $affected === 1;
    }

    public function emailExists(string $email): bool
    {
        return $this->db->fetchOne('SELECT id FROM users WHERE email = :email', [':email' => $email]) !== null;
    }
}
