<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Uuid;
use App\Repositories\UserRepository;

/**
 * RODO Art. 15 + OWASP A07 — pełny flow resetu hasła.
 *
 * 1. `initiate(email, ip)` — generuje token, zapisuje do `password_resets`, wysyła email.
 *    Zwraca true zarówno gdy email istnieje jak i gdy nie istnieje (anti-enumeration).
 * 2. `findValidToken(token)` — zwraca `user_id` jeśli token istnieje, ważny i nieużyty.
 * 3. `complete(token, newPassword, ip)` — weryfikuje token, ustawia nowe hasło,
 *    oznacza token jako used.
 *
 * Token: 64-hex (`bin2hex(random_bytes(32))`), TTL 1h, jednorazowy.
 */
final class PasswordResetService
{
    private const TOKEN_TTL_SECONDS = 3600;

    public function __construct(
        private readonly Database       $db,
        private readonly UserRepository $userRepo,
        private readonly EmailService   $emailService,
    ) {}

    /**
     * Inicjuje reset hasła. Zwraca zawsze true (anti-enumeration —
     * nie ujawniamy czy email istnieje w bazie).
     */
    public function initiate(string $email, ?string $ip = null): bool
    {
        $email = trim(strtolower($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addTimingJitter();
            return true;
        }

        $row = $this->db->fetchOne(
            'SELECT id FROM users WHERE email = ? AND is_active = 1 AND deleted_at IS NULL',
            [$email]
        );
        if ($row === null) {
            // Nie ujawniamy że email nie istnieje
            $this->addTimingJitter();
            return true;
        }

        $userId    = (string)$row['id'];
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_TTL_SECONDS);
        $id        = Uuid::generate();

        $this->db->execute(
            'INSERT INTO password_resets (id, user_id, token, expires_at, ip)
             VALUES (?, ?, ?, ?, ?)',
            [$id, $userId, $token, $expiresAt, $ip]
        );

        $this->emailService->sendPasswordReset($email, $token);
        $this->addTimingJitter();
        return true;
    }

    /**
     * ZAD-4.2: stały delay 50-150ms zaciemnia różnicę w czasie odpowiedzi
     * między "email istnieje" a "email nie istnieje" — uniemożliwia
     * timing-based enumeration. Random_int dla zmniejszenia statystycznej
     * dystrybucji (jeśli atakujący uśrednia tysiące prób).
     */
    private function addTimingJitter(): void
    {
        usleep(random_int(50_000, 150_000));
    }

    /**
     * Zwraca user_id jeśli token jest ważny (istnieje, nie wygasł, nie użyty).
     */
    public function findValidToken(string $token): ?string
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        $row = $this->db->fetchOne(
            'SELECT user_id FROM password_resets
             WHERE token = ? AND used_at IS NULL AND expires_at > NOW()',
            [$token]
        );
        return $row !== null ? (string)$row['user_id'] : null;
    }

    /**
     * Kończy reset — weryfikuje token, ustawia nowe hasło, oznacza token jako used.
     * Throws InvalidArgumentException przy błędzie tokenu lub słabym haśle.
     */
    public function complete(string $token, string $newPassword): void
    {
        if (strlen($newPassword) < 12) {
            throw new \InvalidArgumentException('Hasło musi mieć co najmniej 12 znaków.');
        }

        $userId = $this->findValidToken($token);
        if ($userId === null) {
            throw new \InvalidArgumentException('Link resetu jest nieprawidłowy lub wygasł.');
        }

        $this->db->beginTransaction();
        try {
            $hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);
            $this->userRepo->updatePassword($userId, $hash);

            $this->db->execute(
                'UPDATE password_resets SET used_at = NOW() WHERE token = ?',
                [$token]
            );

            // Inkrementuj session_version żeby unieważnić wszystkie aktywne sesje
            $this->db->execute(
                'UPDATE users SET session_version = session_version + 1 WHERE id = ?',
                [$userId]
            );

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollback();
            }
            throw $e;
        }
    }

}
