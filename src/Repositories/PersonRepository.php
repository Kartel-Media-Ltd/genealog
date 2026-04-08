<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\Person;

class PersonRepository
{
    public function __construct(private readonly Database $db) {}

    /** @return Person[] */
    public function findByTree(string $treeId, string $sortBy = 'last_name'): array
    {
        $allowed = ['last_name', 'first_name', 'birth_date', 'created_at'];
        $order   = in_array($sortBy, $allowed, true) ? $sortBy : 'last_name';

        $rows = $this->db->fetchAll(
            "SELECT * FROM persons WHERE tree_id = ? ORDER BY {$order}, first_name",
            [$treeId]
        );

        return array_map(fn($r) => Person::fromArray($r), $rows);
    }

    /** IDOR-safe: always filters by tree_id */
    public function findById(string $id, string $treeId): ?Person
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM persons WHERE id = ? AND tree_id = ?',
            [$id, $treeId]
        );
        return $row ? Person::fromArray($row) : null;
    }

    /**
     * Wyszukaj osobę wyłącznie po ID — używane w kontekście cross-tree
     * gdzie treeId nie jest znane z góry (np. CrossTreeLinkService).
     * Nie nadaje się do normalnych widoków (brak filtra tree_id — IDOR risk).
     */
    public function findByIdGlobal(string $id): ?Person
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM persons WHERE id = ?',
            [$id]
        );
        return $row ? Person::fromArray($row) : null;
    }

    public function create(string $id, string $treeId, string $createdBy, array $data): void
    {
        $this->db->execute(
            'INSERT INTO persons
             (id, tree_id, first_name, last_name, maiden_name,
              birth_date, birth_place, death_date, death_place,
              gender, is_living, visibility, notes, photo_path, gedcom_xref,
              created_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $id, $treeId,
                $data['first_name'], $data['last_name'], $data['maiden_name'] ?? null,
                $data['birth_date'] ?? null, $data['birth_place'] ?? null,
                $data['death_date'] ?? null, $data['death_place'] ?? null,
                $data['gender'], (int)$data['is_living'], $data['visibility'],
                $data['notes'] ?? null, null, $data['gedcom_xref'] ?? null,
                $createdBy,
            ]
        );
    }

    public function update(string $id, string $treeId, array $data): void
    {
        $this->db->execute(
            'UPDATE persons SET
             first_name = ?, last_name = ?, maiden_name = ?,
             birth_date = ?, birth_place = ?, death_date = ?, death_place = ?,
             gender = ?, is_living = ?, visibility = ?, notes = ?,
             updated_at = NOW()
             WHERE id = ? AND tree_id = ?',
            [
                $data['first_name'], $data['last_name'], $data['maiden_name'] ?? null,
                $data['birth_date'] ?? null, $data['birth_place'] ?? null,
                $data['death_date'] ?? null, $data['death_place'] ?? null,
                $data['gender'], (int)$data['is_living'], $data['visibility'],
                $data['notes'] ?? null,
                $id, $treeId,
            ]
        );
    }

    public function delete(string $id, string $treeId): void
    {
        $this->db->execute(
            'DELETE FROM persons WHERE id = ? AND tree_id = ?',
            [$id, $treeId]
        );
    }

    public function updatePhotoPath(string $id, string $treeId, string $path): void
    {
        $this->db->execute(
            'UPDATE persons SET photo_path = ?, updated_at = NOW() WHERE id = ? AND tree_id = ?',
            [$path, $id, $treeId]
        );
    }

    /**
     * Aktualizuje fingerprint_hash i name_soundex — używane przez Discovery listener
     * po person.created/updated. Nie dotyka updated_at żeby nie wywołać kaskady hooków.
     */
    public function updateFingerprint(string $id, ?string $hash, ?string $soundex): void
    {
        $this->db->execute(
            'UPDATE persons SET fingerprint_hash = ?, name_soundex = ? WHERE id = ?',
            [$hash, $soundex, $id]
        );
    }

    public function findByXref(string $treeId, string $xref): ?Person
    {
        $row = $this->db->fetchOne(
            'SELECT * FROM persons WHERE tree_id = ? AND gedcom_xref = ?',
            [$treeId, $xref]
        );
        return $row ? Person::fromArray($row) : null;
    }

    public function countByTree(string $treeId): int
    {
        $row = $this->db->fetchOne(
            'SELECT COUNT(*) AS cnt FROM persons WHERE tree_id = ?',
            [$treeId]
        );
        return (int)($row['cnt'] ?? 0);
    }
}
