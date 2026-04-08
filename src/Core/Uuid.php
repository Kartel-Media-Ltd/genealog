<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Generator UUID v4 — eliminuje duplikację z 8+ klas (audit K-P1).
 *
 * Wcześniej każdy serwis miał własną metodę `generateUuid()` (AuthService,
 * PersonService, GlobalIndexService, NotificationService, TreeService,
 * GedcomService, InvitationService, RelationshipService, DiscoveryRepository).
 * Wszystkie były identyczne — RFC 4122 v4 z `random_bytes(16)`.
 */
final class Uuid
{
    /**
     * Generuje UUID v4 zgodnie z RFC 4122 (8-4-4-4-12 hex z version + variant bits).
     *
     * Przykład: `550e8400-e29b-41d4-a716-446655440000`
     */
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40); // version 4
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80); // variant RFC 4122
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
