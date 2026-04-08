<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\PersonRepository;
use App\Repositories\RelationshipRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;

/**
 * RODO Art. 20 — Right to data portability.
 *
 * Generuje plik ZIP z wszystkimi danymi konta użytkownika:
 *   - `account.json` — profil (imię, email, locale, ustawienia, daty)
 *   - `notifications.json` — historia powiadomień
 *   - `audit-log.json` — wpisy z source_audit_log dotyczące tego usera
 *   - `tree-{id}.ged` — eksport każdego drzewa (jako sole owner) w formacie GEDCOM
 *
 * Limit: max 1 eksport / 24h per user (rate limit przez `RateLimiter`).
 * Dane żyjących osób w GEDCOM są ograniczone (filter w GedcomService::buildIndi).
 */
final class DataExportService
{
    public function __construct(
        private readonly Database               $db,
        private readonly UserRepository         $userRepo,
        private readonly TreeRepository         $treeRepo,
        private readonly PersonRepository       $personRepo,
        private readonly RelationshipRepository $relRepo,
    ) {}

    /**
     * Generuje plik ZIP w sys_get_temp_dir() i zwraca pełną ścieżkę.
     * Caller jest odpowiedzialny za usunięcie pliku po wysłaniu.
     *
     * ZAD-2.3 (F-04): try/finally gwarantuje cleanup tmpDir nawet przy throw.
     * Failure między zip->open a return → ZIP path jest unlinkowany przed throw.
     */
    public function generateExport(string $userId): string
    {
        $user = $this->userRepo->findById($userId);
        if ($user === null) {
            throw new \RuntimeException('User not found');
        }

        $tmpDir  = sys_get_temp_dir() . '/genealog-export-' . bin2hex(random_bytes(8));
        $zipPath = sys_get_temp_dir() . '/genealog-export-' . bin2hex(random_bytes(8)) . '.zip';

        if (!@mkdir($tmpDir, 0700, true) && !is_dir($tmpDir)) {
            throw new \RuntimeException('Cannot create temp directory for export');
        }

        $success = false;
        try {
            $zip = new \ZipArchive();
            if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
                throw new \RuntimeException('Cannot create ZIP file');
            }

            // 1. account.json
            $zip->addFromString('account.json', json_encode([
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'locale'              => $user->locale ?? 'pl',
                'is_active'           => $user->isActive ?? true,
                'email_notifications' => $user->emailNotifications ?? true,
                'created_at'          => $user->createdAt ?? null,
                'export_generated_at' => date('c'),
                'export_basis'        => 'RODO Art. 20 — right to data portability',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            // 2. notifications.json
            $notifications = $this->db->fetchAll(
                'SELECT id, type, title, body, link, is_read, created_at, read_at
                 FROM notifications WHERE user_id = ? ORDER BY created_at DESC',
                [$userId]
            );
            $zip->addFromString('notifications.json', json_encode(
                $notifications,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // 3. audit-log.json
            $auditLog = $this->db->fetchAll(
                'SELECT action, source_type, source_id, target_person_id, target_tree_id, ip, created_at
                 FROM source_audit_log WHERE user_id = ? ORDER BY created_at DESC',
                [$userId]
            );
            $zip->addFromString('audit-log.json', json_encode(
                $auditLog,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // ZAD-3.1 (P10): rozszerzony eksport — zgodnie z RODO Art. 15 użytkownik ma prawo
            // do wszystkich danych, nie tylko do drzew sole-owned.

            // 3a. invitations.json — zaproszenia wysłane przez usera
            $invitations = $this->db->fetchAll(
                'SELECT id, tree_id, invited_email, role, expires_at, used_at, created_at
                 FROM invitations WHERE invited_by = ? ORDER BY created_at DESC',
                [$userId]
            );
            $zip->addFromString('invitations.json', json_encode(
                $invitations,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // 3b. shared-tree-contributions.json — osoby które user dodał do cudzych drzew
            //     (jako editor). Dane są jego kontrybucją — ma do nich prawo dostępu.
            $sharedContributions = $this->db->fetchAll(
                'SELECT p.id, p.tree_id, t.name AS tree_name, p.first_name, p.last_name,
                        p.birth_date, p.birth_place, p.death_date, p.death_place,
                        p.gender, p.is_living, p.notes, p.created_at
                 FROM persons p
                 INNER JOIN trees t ON p.tree_id = t.id
                 WHERE p.created_by = ? AND t.owner_id != ?',
                [$userId, $userId]
            );
            $zip->addFromString('shared-tree-contributions.json', json_encode(
                $sharedContributions,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // ZAD-2.6 (P6): tree-memberships.json — drzewa gdzie user jest członkiem (editor/viewer).
            // RODO Art. 15: user ma prawo wiedzieć w których drzewach jest członkiem, z jaką rolą,
            // kto go zaprosił. Wcześniejsze sekcje eksportują tylko trees sole-owned.
            $memberships = $this->db->fetchAll(
                'SELECT tm.tree_id, tm.role, tm.invited_at, tm.invited_by, t.name AS tree_name
                 FROM tree_members tm
                 INNER JOIN trees t ON t.id = tm.tree_id
                 WHERE tm.user_id = ?',
                [$userId]
            );
            $zip->addFromString('tree-memberships.json', json_encode(
                $memberships,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // ZAD-2.6 (P6): password-resets.json — historia żądań resetu hasła.
            // Bez kolumny `token` (secret) — tylko metadata (IP, daty). User może
            // zobaczyć czy ktoś nieautoryzowany żądał resetu z obcego IP.
            $passwordResets = $this->db->fetchAll(
                'SELECT id, expires_at, used_at, ip, created_at
                 FROM password_resets WHERE user_id = ? ORDER BY created_at DESC',
                [$userId]
            );
            $zip->addFromString('password-resets.json', json_encode(
                $passwordResets,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // 3c. discovery-suggestions.json — sugestie dopasowań Discovery dotyczące usera
            $discoverySuggestions = $this->db->fetchAll(
                "SELECT pms.*
                 FROM person_match_suggestions pms
                 INNER JOIN persons p ON pms.person_id = p.id
                 INNER JOIN trees t ON p.tree_id = t.id
                 WHERE t.owner_id = ?
                    OR EXISTS (SELECT 1 FROM tree_members tm WHERE tm.tree_id = t.id AND tm.user_id = ?)",
                [$userId, $userId]
            );
            $zip->addFromString('discovery-suggestions.json', json_encode(
                $discoverySuggestions,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            ));

            // 4. trees as GEDCOM (tylko sole-owned)
            $trees = $this->treeRepo->findByOwner($userId);
            $pdo   = $this->db->getPdo();
            $gedcomService = new GedcomService($this->personRepo, $this->relRepo, $pdo);

            foreach ($trees as $tree) {
                try {
                    $gedcomData = $gedcomService->export($tree->id);
                    $safeName   = preg_replace('/[^a-z0-9\-_]/i', '-', $tree->name) ?: 'tree';
                    $zip->addFromString("trees/tree-{$safeName}-{$tree->id}.ged", $gedcomData);
                } catch (\Throwable $e) {
                    error_log("DataExport: failed to export tree {$tree->id}: " . $e->getMessage());
                    $zip->addFromString(
                        "trees/tree-{$tree->id}-ERROR.txt",
                        "Eksport drzewa nie powiódł się: " . $e->getMessage()
                    );
                }
            }

            // 5. README
            $dpoContact = defined('DPO_EMAIL') ? DPO_EMAIL : '[do uzupełnienia w config]';
            $zip->addFromString('README.txt',
                "Genealog — Eksport danych konta\n"
                . "===================================\n\n"
                . "Plik wygenerowany: " . date('Y-m-d H:i:s') . "\n"
                . "Podstawa prawna: RODO (UE) 2016/679, Art. 15 + Art. 20\n\n"
                . "Zawartość:\n"
                . "  - account.json                    — dane Twojego profilu\n"
                . "  - notifications.json              — historia powiadomień\n"
                . "  - audit-log.json                  — historia akcji w systemie (RODO Art. 30)\n"
                . "  - invitations.json                — wysłane zaproszenia do drzew\n"
                . "  - shared-tree-contributions.json  — osoby dodane przez Ciebie do cudzych drzew\n"
                . "  - discovery-suggestions.json      — sugestie dopasowań z globalnego indeksu\n"
                . "  - tree-memberships.json           — drzewa gdzie jesteś członkiem (editor/viewer)\n"
                . "  - password-resets.json            — historia żądań resetu hasła (bez tokenów)\n"
                . "  - trees/                          — drzewa sole-owned w formacie GEDCOM 5.5.1\n\n"
                . "GEDCOM 5.5.1 jest standardem branżowym — pliki .ged można zaimportować\n"
                . "do dowolnego programu genealogicznego (Ancestry, MyHeritage, FamilySearch, Gramps, etc.).\n\n"
                . "Uwaga: dane żyjących osób w plikach GEDCOM są ograniczone do imienia,\n"
                . "nazwiska i roku urodzenia (RODO Art. 25 — privacy by design).\n\n"
                . "Jeśli masz pytania dotyczące zawartości eksportu lub brakuje jakichś danych,\n"
                . "skontaktuj się z administratorem danych: " . $dpoContact . "\n"
            );

            $zip->close();
            $success = true;

            return $zipPath;
        } finally {
            // Cleanup tmpDir — zawsze (nawet po wyjątku)
            if (is_dir($tmpDir)) {
                $files = glob($tmpDir . '/*') ?: [];
                foreach ($files as $f) {
                    @unlink($f);
                }
                @rmdir($tmpDir);
            }
            // Jeśli generacja się NIE powiodła — nie zostawiaj częściowego ZIP-a
            if (!$success && is_file($zipPath)) {
                @unlink($zipPath);
            }
        }
    }
}
