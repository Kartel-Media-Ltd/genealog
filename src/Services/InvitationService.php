<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\InvitationRepository;
use App\Repositories\TreeRepository;
use App\Repositories\UserRepository;

class InvitationService
{
    public function __construct(
        private readonly TreeRepository       $treeRepo,
        private readonly InvitationRepository $invRepo,
        private readonly UserRepository       $userRepo,
        private readonly EmailService         $emailSvc,
    ) {}

    public function invite(
        string $treeId,
        string $email,
        string $role,
        string $inviterId,
    ): void {
        // Defense in depth: verify owner in service too
        if (!$this->treeRepo->isOwner($treeId, $inviterId)) {
            throw new \RuntimeException('Tylko właściciel może zapraszać.');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Nieprawidłowy adres e-mail.');
        }

        if (!in_array($role, ['editor', 'viewer'], true)) {
            throw new \InvalidArgumentException('Nieprawidłowa rola.');
        }

        // Guard: active invitation already exists
        if ($this->invRepo->findActiveByEmailAndTree($email, $treeId) !== null) {
            throw new \InvalidArgumentException('Zaproszenie dla tego adresu e-mail już oczekuje.');
        }

        // Guard: user already a member — look up by email
        $existingUser = $this->userRepo->findByEmail($email);
        if ($existingUser !== null && $this->treeRepo->getUserRole($treeId, $existingUser->id) !== null) {
            throw new \InvalidArgumentException('Użytkownik jest już członkiem tego drzewa.');
        }

        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        $id        = $this->generateUuid();

        $this->invRepo->create($id, $treeId, $inviterId, $email, $token, $role, $expiresAt);

        // Get tree + inviter name for email
        $tree        = $this->treeRepo->findById($treeId);
        $inviter     = $this->userRepo->findById($inviterId);
        $treeName    = $tree?->name ?? 'drzewo genealogiczne';
        $inviterName = $inviter?->name ?? 'Użytkownik';

        $this->emailSvc->sendInvitation($email, $token, $treeName, $inviterName);
    }

    public function findValidByToken(string $token): ?array
    {
        $inv = $this->invRepo->findByToken($token);

        if ($inv === null) {
            return null;
        }
        if ($inv['used_at'] !== null) {
            return null;
        }
        if (strtotime($inv['expires_at']) < time()) {
            return null;
        }

        return $inv;
    }

    public function accept(string $token, string $userId): string
    {
        $inv = $this->findValidByToken($token);
        if ($inv === null) {
            throw new \RuntimeException('Link zaproszenia wygasł lub jest nieprawidłowy.');
        }

        $treeId = $inv['tree_id'];

        // Idempotent: if already a member, just mark used and return
        if ($this->treeRepo->getUserRole($treeId, $userId) !== null) {
            $this->invRepo->markUsed($token);
            return $treeId;
        }

        $invitedBy = $inv['invited_by'] ?? null;
        $this->invRepo->insertMember($treeId, $userId, $inv['role'], $invitedBy);
        $this->invRepo->markUsed($token);

        return $treeId;
    }

    public function removeMember(string $treeId, string $targetUserId, string $requesterId): void
    {
        if (!$this->treeRepo->isOwner($treeId, $requesterId)) {
            throw new \RuntimeException('Tylko właściciel może usuwać członków.');
        }

        $tree = $this->treeRepo->findById($treeId);
        if ($tree !== null && $tree->ownerId === $targetUserId) {
            throw new \RuntimeException('Nie można usunąć właściciela drzewa.');
        }

        $this->invRepo->deleteMember($treeId, $targetUserId);
    }

    public function changeRole(
        string $treeId,
        string $targetUserId,
        string $newRole,
        string $requesterId,
    ): void {
        if (!$this->treeRepo->isOwner($treeId, $requesterId)) {
            throw new \RuntimeException('Tylko właściciel może zmieniać role.');
        }

        $tree = $this->treeRepo->findById($treeId);
        if ($tree !== null && $tree->ownerId === $targetUserId) {
            throw new \RuntimeException('Nie można zmienić roli właściciela.');
        }

        if (!in_array($newRole, ['editor', 'viewer'], true)) {
            throw new \InvalidArgumentException('Nieprawidłowa rola.');
        }

        $this->invRepo->updateMemberRole($treeId, $targetUserId, $newRole);
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
