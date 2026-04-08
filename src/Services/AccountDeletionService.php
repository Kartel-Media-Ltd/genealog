<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;

/**
 * RODO Art. 17 — Right to erasure. Anonimizuje konto użytkownika i powiązane dane.
 *
 * Strategia (kompromis między prawem do usunięcia a integralnością audit log):
 *
 *   1. Anonimizacja PII konta (email → `deleted-{uuid}@deleted.local`, name → `[Usunięto]`,
 *      password_hash → '', is_active=0, deleted_at=NOW()).
 *   2. Usunięcie sole-owned drzew genealogicznych user'a (cascade do persons + relationships
 *      via FK ON DELETE CASCADE w obrębie tree).
 *   3. Drzewa współdzielone (gdzie user był członkiem) — usunięcie user'a z `tree_members`,
 *      drzewa pozostają.
 *   4. Audit log — `source_audit_log.user_id` ma `ON DELETE SET NULL` (migracja 010),
 *      więc anonimizacja zostawia historyczne wpisy z `user_id = NULL`.
 *
 * Wszystko w transakcji. Failure → rollback całości.
 */
final class AccountDeletionService
{
    public function __construct(
        private readonly Database       $db,
        private readonly UserRepository $userRepo,
        private readonly TreeRepository $treeRepo,
    ) {}

    /**
     * Wykonuje pełną anonimizację konta. Throws RuntimeException przy partial failure.
     */
    public function deleteAccount(string $userId): void
    {
        // ZAD-4.1: anonimizacja może trwać długo (cleanup wielu drzew + plików)
        @set_time_limit(300);

        $this->db->beginTransaction();
        try {
            // 1. Znajdź drzewa sole-owned (bez innych członków z rolą owner/editor)
            //    ZAD-1.2 (K2): wcześniej query zwracała WSZYSTKIE `WHERE owner_id = ?` —
            //    usuwała drzewa innych współpracowników. Teraz filtruje z NOT EXISTS.
            $soleOwnedTreeIds = $this->findSoleOwnedTreeIds($userId);

            // 1a. ZAD-1.2 (K2): drzewa współdzielone (gdzie user jest ownerem, ale inni mają
            //     rolę owner/editor) → transfer ownership do pierwszego innego współpracownika
            //     zamiast kasowania. Sprawiedliwsze i zgodne z intuicją użytkownika.
            $sharedOwnedTreeIds = $this->findSharedOwnedTreeIds($userId);
            foreach ($sharedOwnedTreeIds as $treeId) {
                $this->transferOwnership($treeId, $userId);
            }

            // 2. Usuń drzewa sole-owned (cascade do persons, relationships, etc.
            //    poprzez FK ON DELETE RESTRICT — najpierw musimy ręcznie kasować zależności)
            foreach ($soleOwnedTreeIds as $treeId) {
                $this->deleteTreeCascade($treeId);
            }

            // 2a. ZAD-4.1: usuń pliki fizyczne (zdjęcia osób) z storage/media/{tree_id}/
            // To jest poza transakcją DB ale wewnątrz try — failure → rollback DB,
            // a pliki które już usunęliśmy zostają usunięte (akceptowalne, są one
            // duplikatami zanonimizowanych danych).
            foreach ($soleOwnedTreeIds as $treeId) {
                $this->deleteTreeMediaFiles($treeId);
            }

            // 3. Usuń user'a z tree_members (drzewa współdzielone — drzewa pozostają)
            $this->db->execute('DELETE FROM tree_members WHERE user_id = ?', [$userId]);

            // 4. Usuń notyfikacje
            $this->db->execute('DELETE FROM notifications WHERE user_id = ?', [$userId]);

            // 4a. F-01: Usuń aktywne tokeny resetu hasła. Kluczowe — bez tego atakujący
            // z przejętym mailem mógłby użyć starego tokenu, by ustawić nowe hasło i
            // zalogować się na anonimowane konto. ON DELETE CASCADE FK nie odpala
            // bo to UPDATE (anonimizacja), nie DELETE z `users`.
            $this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$userId]);

            // 5. Anuluj zaproszenia wysłane przez user'a (jeśli istnieją)
            $this->db->execute(
                'UPDATE invitations SET used_at = NOW() WHERE invited_by = ? AND used_at IS NULL',
                [$userId]
            );

            // 6. source_audit_log — pozostaje (FK ON DELETE SET NULL po migracji 010)

            // 7. Anonimizacja samej tabeli users
            $this->userRepo->anonymize($userId);

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw new \RuntimeException(
                'Account deletion failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Drzewa gdzie ten user jest jedynym ownerem — nie ma innych userów w tree_members
     * z rolą owner/editor (viewer nie wystarcza, bo nie może kontynuować pracy nad drzewem).
     *
     * ZAD-1.2 (K2): wcześniej zwracało WSZYSTKIE drzewa `WHERE owner_id = ?` (nawet te
     * z innymi współpracownikami). `deleteTreeCascade` kasowało je — utrata cudzych danych.
     *
     * @return list<string>
     */
    private function findSoleOwnedTreeIds(string $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.id FROM trees t
             WHERE t.owner_id = ?
               AND NOT EXISTS (
                   SELECT 1 FROM tree_members tm
                   WHERE tm.tree_id = t.id
                     AND tm.user_id != ?
                     AND tm.role IN ('owner','editor')
               )",
            [$userId, $userId]
        );
        return array_map(static fn(array $r): string => (string)$r['id'], $rows);
    }

    /**
     * ZAD-1.2 (K2): drzewa gdzie user jest właścicielem, ale MA co-ownerów/edytorów.
     * Dla nich wykonujemy transfer ownership zamiast cascade delete.
     *
     * @return list<string>
     */
    private function findSharedOwnedTreeIds(string $userId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT t.id FROM trees t
             WHERE t.owner_id = ?
               AND EXISTS (
                   SELECT 1 FROM tree_members tm
                   WHERE tm.tree_id = t.id
                     AND tm.user_id != ?
                     AND tm.role IN ('owner','editor')
               )",
            [$userId, $userId]
        );
        return array_map(static fn(array $r): string => (string)$r['id'], $rows);
    }

    /**
     * ZAD-1.2 (K2): przekazuje ownership drzewa na pierwszego innego współpracownika.
     * Kolejność: najpierw `owner`, potem `editor`, w ramach każdej roli — wg `invited_at`.
     * `viewer` jest wykluczony, bo nie może edytować drzewa (user removal musi zostawić
     * drzewo w rękach kogoś zdolnego do jego kontynuowania).
     *
     * Zakłada że caller wywołał `findSharedOwnedTreeIds()` — więc zawsze istnieje kandydat.
     */
    private function transferOwnership(string $treeId, string $fromUserId): void
    {
        $newOwner = $this->db->fetchOne(
            "SELECT user_id FROM tree_members
             WHERE tree_id = ? AND user_id != ?
               AND role IN ('owner','editor')
             ORDER BY FIELD(role, 'owner', 'editor'), invited_at ASC
             LIMIT 1",
            [$treeId, $fromUserId]
        );

        if ($newOwner === null) {
            // Nie powinno się zdarzyć — caller gwarantuje istnienie.
            // Defensive: jeśli się zdarzy, loguj i pomiń transfer (drzewo pozostaje nienaruszone).
            error_log("AccountDeletionService: transferOwnership called on tree {$treeId} without eligible successor");
            return;
        }

        $newOwnerId = (string)$newOwner['user_id'];

        // Nowy owner: promocja w trees + aktualizacja roli w tree_members
        $this->db->execute(
            'UPDATE trees SET owner_id = ? WHERE id = ?',
            [$newOwnerId, $treeId]
        );
        $this->db->execute(
            "UPDATE tree_members SET role = 'owner' WHERE tree_id = ? AND user_id = ?",
            [$treeId, $newOwnerId]
        );
    }

    /**
     * Usuwa drzewo wraz z osobami, relacjami, członkami i indeksami discovery.
     * Wymaga manualnego kaskadowania bo FK są ON DELETE RESTRICT (zasada bezpieczeństwa
     * danych genealogicznych).
     */
    private function deleteTreeCascade(string $treeId): void
    {
        // Discovery indexes
        $this->db->execute('DELETE FROM global_person_index WHERE tree_id = ?', [$treeId]);
        $this->db->execute(
            'DELETE FROM person_match_suggestions
             WHERE person_id IN (SELECT id FROM persons WHERE tree_id = ?)',
            [$treeId]
        );

        // Relationships
        $this->db->execute('DELETE FROM relationships WHERE tree_id = ?', [$treeId]);

        // Persons
        $this->db->execute('DELETE FROM persons WHERE tree_id = ?', [$treeId]);

        // Tree members
        $this->db->execute('DELETE FROM tree_members WHERE tree_id = ?', [$treeId]);

        // Invitations
        $this->db->execute('DELETE FROM invitations WHERE tree_id = ?', [$treeId]);

        // Tree itself
        $this->db->execute('DELETE FROM trees WHERE id = ?', [$treeId]);
    }

    /**
     * ZAD-4.1: usuwa fizyczny katalog mediów drzewa (storage/media/{tree_id}/).
     * Nie rzuca wyjątku przy braku katalogu — drzewo mogło być bez zdjęć.
     * Wszystkie unlink() są @-tłumione, bo failure pojedynczego pliku
     * nie powinno zatrzymywać całego cleanupu RODO.
     */
    private function deleteTreeMediaFiles(string $treeId): void
    {
        $storageRoot = defined('STORAGE_PATH')
            ? STORAGE_PATH
            : dirname(__DIR__, 2) . '/storage';

        $mediaDir = $storageRoot . '/media/' . $treeId;
        if (!is_dir($mediaDir)) {
            return;
        }

        $files = glob($mediaDir . '/*') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        @rmdir($mediaDir);
    }
}
