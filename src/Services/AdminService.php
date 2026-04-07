<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Session;
use App\Models\User;
use App\Repositories\AdminRepository;
use App\Repositories\UserRepository;

class AdminService
{
    /** UUID v4 format regex */
    private const UUID_REGEX = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(
        private readonly UserRepository  $userRepo,
        private readonly AdminRepository $adminRepo,
    ) {}

    /**
     * Verify caller is currently an active admin (DB read, not session).
     * Used as guard at the start of every sensitive action.
     */
    private function assertCallerIsAdmin(string $adminId): User
    {
        $admin = $this->userRepo->findById($adminId);
        if ($admin === null) {
            throw new \RuntimeException('Konto administratora nie istnieje.');
        }
        if (!$admin->isAdmin) {
            throw new \RuntimeException('Twoje uprawnienia administratora zostały cofnięte.');
        }
        if ($admin->isBlocked) {
            throw new \RuntimeException('Twoje konto zostało zablokowane.');
        }
        return $admin;
    }

    /** Validate UUID v4 format to prevent garbage in admin_logs.target_id */
    private function assertValidUuid(string $id, string $field = 'identyfikator'): void
    {
        if (!preg_match(self::UUID_REGEX, $id)) {
            throw new \InvalidArgumentException("Nieprawidłowy {$field}.");
        }
    }

    /**
     * Start impersonating a user.
     * Saves admin data in session, swaps to target user session.
     * Returns the target User for session setup by caller.
     */
    public function impersonate(string $adminId, string $targetUserId): User
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');
        $this->assertValidUuid($targetUserId, 'identyfikator użytkownika');
        $this->assertCallerIsAdmin($adminId);

        if ($adminId === $targetUserId) {
            throw new \InvalidArgumentException('Nie można impersonować samego siebie.');
        }

        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) {
            throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        }
        if ($target->isAdmin) {
            throw new \InvalidArgumentException('Nie można impersonować administratora.');
        }
        if ($target->isBlocked) {
            throw new \InvalidArgumentException('Nie można impersonować zablokowanego konta.');
        }

        $this->logAction($adminId, 'impersonate_start', 'user', $targetUserId, [
            'target_email' => $target->email,
            'target_name'  => $target->name,
        ]);

        return $target;
    }

    /**
     * End impersonation. Re-verifies admin still has admin rights from DB.
     * Returns the admin User for session restore by caller.
     * Throws when admin lost privileges during impersonation — caller must destroy session.
     */
    public function exitImpersonate(string $adminId, string $impersonatedUserId): User
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');

        $admin = $this->userRepo->findById($adminId);
        if ($admin === null) {
            throw new \RuntimeException('Konto admina nie istnieje.');
        }
        if (!$admin->isAdmin) {
            throw new \RuntimeException('Uprawnienia administratora zostały cofnięte podczas impersonacji.');
        }
        if ($admin->isBlocked) {
            throw new \RuntimeException('Konto administratora zostało zablokowane podczas impersonacji.');
        }

        $this->logAction($adminId, 'impersonate_end', 'user', $impersonatedUserId);

        return $admin;
    }

    public function block(string $adminId, string $targetUserId): void
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');
        $this->assertValidUuid($targetUserId, 'identyfikator użytkownika');
        $this->assertCallerIsAdmin($adminId);

        if ($adminId === $targetUserId) {
            throw new \InvalidArgumentException('Nie można zablokować własnego konta.');
        }

        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) {
            throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        }
        if ($target->isAdmin) {
            throw new \InvalidArgumentException('Nie można zablokować konta administratora.');
        }
        if ($target->isBlocked) {
            throw new \InvalidArgumentException('Konto jest już zablokowane.');
        }

        $this->userRepo->setBlocked($targetUserId, true);
        $this->logAction($adminId, 'block', 'user', $targetUserId, [
            'target_email' => $target->email,
        ]);
    }

    public function unblock(string $adminId, string $targetUserId): void
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');
        $this->assertValidUuid($targetUserId, 'identyfikator użytkownika');
        $this->assertCallerIsAdmin($adminId);

        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) {
            throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        }
        if (!$target->isBlocked) {
            throw new \InvalidArgumentException('Konto nie jest zablokowane.');
        }

        $this->userRepo->setBlocked($targetUserId, false);
        $this->logAction($adminId, 'unblock', 'user', $targetUserId, [
            'target_email' => $target->email,
        ]);
    }

    public function promote(string $adminId, string $targetUserId): void
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');
        $this->assertValidUuid($targetUserId, 'identyfikator użytkownika');
        $this->assertCallerIsAdmin($adminId);

        if ($adminId === $targetUserId) {
            throw new \InvalidArgumentException('Jesteś już administratorem.');
        }

        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) {
            throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        }
        if ($target->isAdmin) {
            throw new \InvalidArgumentException('Użytkownik jest już administratorem.');
        }
        if ($target->isBlocked) {
            throw new \InvalidArgumentException('Nie można promować zablokowanego konta. Najpierw odblokuj.');
        }

        $this->userRepo->setAdmin($targetUserId, true);
        $this->logAction($adminId, 'promote', 'user', $targetUserId, [
            'target_email' => $target->email,
        ]);
    }

    public function demote(string $adminId, string $targetUserId): void
    {
        $this->assertValidUuid($adminId, 'identyfikator administratora');
        $this->assertValidUuid($targetUserId, 'identyfikator użytkownika');
        $this->assertCallerIsAdmin($adminId);

        if ($adminId === $targetUserId) {
            throw new \InvalidArgumentException('Nie można zdegradować własnego konta.');
        }

        $target = $this->userRepo->findById($targetUserId);
        if ($target === null) {
            throw new \InvalidArgumentException('Użytkownik nie istnieje.');
        }
        if (!$target->isAdmin) {
            throw new \InvalidArgumentException('Użytkownik nie jest administratorem.');
        }

        $this->userRepo->setAdmin($targetUserId, false);
        $this->logAction($adminId, 'demote', 'user', $targetUserId, [
            'target_email' => $target->email,
        ]);
    }

    public function logAction(
        string  $adminId,
        string  $action,
        ?string $targetType = null,
        ?string $targetId   = null,
        array   $meta       = [],
    ): void {
        $id       = $this->generateUuid();
        $metaJson = empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE);
        $this->adminRepo->createLog($id, $adminId, $action, $targetType, $targetId, $metaJson);
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
