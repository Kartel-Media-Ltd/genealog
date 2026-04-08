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

    public function setAdmin(string $id, bool $isAdmin): void
    {
        // Inkrementacja session_version unieważnia wszystkie istniejące sesje usera —
        // zdegradowany admin nie zachowa uprawnień w starej sesji.
        $this->db->execute(
            'UPDATE users SET is_admin = ?, session_version = session_version + 1 WHERE id = ?',
            [(int)$isAdmin, $id]
        );
    }

    public function setBlocked(string $id, bool $isBlocked): void
    {
        $this->db->execute(
            'UPDATE users SET is_blocked = ?, session_version = session_version + 1 WHERE id = ?',
            [(int)$isBlocked, $id]
        );
    }

    /**
     * ZAD-3.2 (P11): RODO Art. 18 — right to restriction of processing.
     * Restricted account = blokada logowania bez pełnej anonimizacji danych.
     * Użytkownik może wycofać ograniczenie po zalogowaniu (confirm flow).
     * Session invalidation przez session_version++ — natychmiast wylogowuje.
     */
    public function setRestricted(string $id, bool $isRestricted): void
    {
        $this->db->execute(
            'UPDATE users
             SET is_restricted = ?,
                 restricted_at = ?,
                 session_version = session_version + 1
             WHERE id = ?',
            [(int)$isRestricted, $isRestricted ? date('Y-m-d H:i:s') : null, $id]
        );
    }

    /**
     * Inkrementuje session_version użytkownika — wszystkie istniejące sesje stają się nieważne.
     * Używaj przy zmianie hasła, podejrzeniu kompromitacji konta, etc.
     */
    public function incrementSessionVersion(string $id): void
    {
        $this->db->execute(
            'UPDATE users SET session_version = session_version + 1 WHERE id = ?',
            [$id]
        );
    }

    public function getSessionVersion(string $id): ?int
    {
        $row = $this->db->fetchOne(
            'SELECT session_version FROM users WHERE id = ?',
            [$id]
        );
        return $row !== null ? (int)$row['session_version'] : null;
    }

    public function findByIdWithHash(string $id): ?array
    {
        return $this->db->fetchOne('SELECT * FROM users WHERE id = :id AND is_active = 1', [':id' => $id]);
    }

    public function updateName(string $id, string $name): void
    {
        $this->db->execute('UPDATE users SET name = ? WHERE id = ?', [$name, $id]);
    }

    public function updateEmail(string $id, string $email): void
    {
        $this->db->execute('UPDATE users SET email = ? WHERE id = ?', [$email, $id]);
    }

    public function updatePassword(string $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function updateLocale(string $id, string $locale): void
    {
        $this->db->execute('UPDATE users SET locale = ? WHERE id = ?', [$locale, $id]);
    }

    public function updateEmailNotifications(string $id, bool $value): void
    {
        $this->db->execute('UPDATE users SET email_notifications = ? WHERE id = ?', [(int)$value, $id]);
    }

    public function deactivate(string $id): void
    {
        $this->db->execute('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
    }

    /**
     * Important #4: dedykowana metoda zamiast surowego SQL w GlobalIndexService.
     * Zwraca true gdy user wyraził zgodę na cross-tree discovery (RODO Art. 7 — opt-in).
     */
    public function isDiscoveryOptedIn(string $id): bool
    {
        $row = $this->db->fetchOne(
            'SELECT discovery_opt_in FROM users WHERE id = ? AND is_active = 1',
            [$id]
        );
        return $row !== null && (int)$row['discovery_opt_in'] === 1;
    }

    /**
     * RODO Art. 17 — anonimizacja konta. Zastępuje email i imię pseudo-wartościami,
     * nullifikuje password_hash, ustawia is_active=0 i deleted_at=NOW().
     * UNIQUE constraint na email zachowany przez pełny `deleted-{uuid}@deleted.local`
     * (F-02: substr(0,8) generował kolizje dla userów z identyczną pierwszą grupą hex).
     */
    public function anonymize(string $id): void
    {
        $this->db->execute(
            'UPDATE users
             SET email = ?, name = ?, password_hash = ?, is_active = 0, deleted_at = NOW()
             WHERE id = ?',
            ["deleted-{$id}@deleted.local", '[Usunięto]', '', $id]
        );
    }
}
