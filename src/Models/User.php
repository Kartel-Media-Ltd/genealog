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
        public readonly string $createdAt,
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
            createdAt:          $data['created_at']          ?? '',
        );
    }
}
