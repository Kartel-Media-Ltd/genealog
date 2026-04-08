<?php
declare(strict_types=1);

namespace App\Models;

class CrossTreeLink
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACCEPTED  = 'accepted';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BROKEN    = 'broken';

    public const VISIBILITY_BASIC = 'shared_basic';
    public const VISIBILITY_FULL  = 'shared_full';

    public function __construct(
        public readonly string  $id,
        public readonly string  $requesterPersonId,
        public readonly string  $targetPersonId,
        public readonly string  $requesterUserId,
        public readonly string  $targetUserId,
        public readonly string  $status,
        public readonly string  $visibilityLevel,
        public readonly ?string $note,
        public readonly string  $createdAt,
        public readonly string  $updatedAt,
    ) {}

    public static function fromArray(array $d): self
    {
        return new self(
            id:                $d['id'],
            requesterPersonId: $d['requester_person_id'],
            targetPersonId:    $d['target_person_id'],
            requesterUserId:   $d['requester_user_id'],
            targetUserId:      $d['target_user_id'],
            status:            $d['status'],
            visibilityLevel:   $d['visibility_level'],
            note:              $d['note'] ?? null,
            createdAt:         $d['created_at'],
            updatedAt:         $d['updated_at'],
        );
    }

    public function isPending(): bool   { return $this->status === self::STATUS_PENDING; }
    public function isAccepted(): bool  { return $this->status === self::STATUS_ACCEPTED; }
    public function isBroken(): bool    { return $this->status === self::STATUS_BROKEN; }

    /** Czy dany userId jest stroną tego połączenia */
    public function involveUser(string $userId): bool
    {
        return $this->requesterUserId === $userId || $this->targetUserId === $userId;
    }

    /** Czy dany userId jest adresatem prośby (może ją zaakceptować) */
    public function isTargetUser(string $userId): bool
    {
        return $this->targetUserId === $userId;
    }

    /** Czy dany userId wysłał prośbę */
    public function isRequesterUser(string $userId): bool
    {
        return $this->requesterUserId === $userId;
    }
}
