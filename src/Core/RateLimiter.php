<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Rate limiter z customowymi limitami per endpoint, oparty na tabeli `rate_limits`.
 *
 * Używane przez `AuthService` (login/register z globalnymi limitami z config/config.php)
 * oraz `DiscoveryController::search` (30/min per IP). Wcześniej logika była duplikowana
 * między AuthService i DiscoveryController — teraz jedna klasa.
 */
final class RateLimiter
{
    public function __construct(private readonly Database $db) {}

    /**
     * Czy użytkownik przekroczył limit zapytań dla endpointu?
     * Zwraca true gdy SUM(attempts) w oknie czasowym >= $maxAttempts.
     */
    public function isLimited(string $ip, string $endpoint, int $maxAttempts, int $windowSeconds): bool
    {
        $row = $this->db->fetchOne(
            'SELECT SUM(attempts) AS total FROM rate_limits
             WHERE ip = :ip AND endpoint = :endpoint
               AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)',
            [':ip' => $ip, ':endpoint' => $endpoint, ':window' => $windowSeconds]
        );
        return (int)($row['total'] ?? 0) >= $maxAttempts;
    }

    /**
     * Inkrementuj licznik prób dla danej pary (ip, endpoint).
     * Używa `ON DUPLICATE KEY UPDATE` — wymaga indeksu lub UNIQUE key.
     */
    public function record(string $ip, string $endpoint): void
    {
        $this->db->execute(
            'INSERT INTO rate_limits (ip, endpoint, attempts) VALUES (:ip, :endpoint, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1',
            [':ip' => $ip, ':endpoint' => $endpoint]
        );
    }
}
