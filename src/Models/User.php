<?php
declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
        public readonly string $name,
        public readonly string $locale,
        public readonly bool   $isActive,
        public readonly bool   $emailNotifications,
        public readonly bool   $isAdmin,
        public readonly bool   $isBlocked,
        public readonly string $createdAt,
        public readonly int    $sessionVersion = 0,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            id:                 $data['id'],
            email:              $data['email'],
            name:               $data['name'],
            locale:             $data['locale']              ?? 'pl',
            isActive:           (bool)($data['is_active']   ?? true),
            emailNotifications: (bool)($data['email_notifications'] ?? true),
            isAdmin:            (bool)($data['is_admin']    ?? false),
            isBlocked:          (bool)($data['is_blocked']  ?? false),
            createdAt:          $data['created_at']          ?? '',
            sessionVersion:     (int)($data['session_version'] ?? 0),
        );
    }
}
