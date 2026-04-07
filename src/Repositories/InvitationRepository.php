<?php
declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

class InvitationRepository
{
    public function __construct(private readonly Database $db) {}

    public function create(
        string $id,
        string $treeId,
        string $invitedBy,
        string $invitedEmail,
        string $token,
        string $role,
        string $expiresAt,
    ): void {
        $this->db->execute(
            'INSERT INTO invitations (id, tree_id, invited_by, invited_email, token, role, expires_at)
             VALUES (:id, :tree_id, :invited_by, :invited_email, :token, :role, :expires_at)',
            [
                ':id'            => $id,
                ':tree_id'       => $treeId,
                ':invited_by'    => $invitedBy,
                ':invited_email' => $invitedEmail,
                ':token'         => $token,
                ':role'          => $role,
                ':expires_at'    => $expiresAt,
            ]
        );
    }

    public function findByToken(string $token): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM invitations WHERE token = :token LIMIT 1',
            [':token' => $token]
        );
    }

    public function findActiveByEmailAndTree(string $email, string $treeId): ?array
    {
        return $this->db->fetchOne(
            'SELECT * FROM invitations
             WHERE invited_email = :email
               AND tree_id = :tid
               AND used_at IS NULL
               AND expires_at > NOW()
             LIMIT 1',
            [':email' => $email, ':tid' => $treeId]
        );
    }

    public function findActiveByTree(string $treeId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM invitations
             WHERE tree_id = :tid
               AND used_at IS NULL
               AND expires_at > NOW()
             ORDER BY created_at DESC',
            [':tid' => $treeId]
        );
    }

    public function markUsed(string $token): void
    {
        $this->db->execute(
            'UPDATE invitations SET used_at = NOW() WHERE token = :token',
            [':token' => $token]
        );
    }

    public function getMembers(string $treeId): array
    {
        return $this->db->fetchAll(
            'SELECT tm.tree_id, tm.user_id, tm.role, tm.invited_at, tm.invited_by,
                    u.name, u.email
             FROM tree_members tm
             JOIN users u ON u.id = tm.user_id
             WHERE tm.tree_id = :tid
             ORDER BY FIELD(tm.role, "owner", "editor", "viewer"), u.name',
            [':tid' => $treeId]
        );
    }

    public function insertMember(string $treeId, string $userId, string $role, ?string $invitedBy): void
    {
        $this->db->execute(
            'INSERT INTO tree_members (tree_id, user_id, role, invited_by)
             VALUES (:tid, :uid, :role, :invited_by)
             ON DUPLICATE KEY UPDATE role = :role2',
            [':tid' => $treeId, ':uid' => $userId, ':role' => $role, ':invited_by' => $invitedBy, ':role2' => $role]
        );
    }

    public function deleteMember(string $treeId, string $userId): void
    {
        $this->db->execute(
            "DELETE FROM tree_members
             WHERE tree_id = :tid AND user_id = :uid AND role != 'owner'",
            [':tid' => $treeId, ':uid' => $userId]
        );
    }

    public function updateMemberRole(string $treeId, string $userId, string $role): void
    {
        $this->db->execute(
            "UPDATE tree_members SET role = :role
             WHERE tree_id = :tid AND user_id = :uid AND role != 'owner'",
            [':role' => $role, ':tid' => $treeId, ':uid' => $userId]
        );
    }
}
