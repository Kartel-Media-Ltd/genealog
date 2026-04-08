<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\RateLimiter;
use App\Core\Uuid;
use App\Models\User;
use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly Database       $db,
        private readonly ?RateLimiter   $rateLimiter = null,
    ) {}

    /**
     * Rejestracja użytkownika.
     *
     * @param string|null $ip Adres IP do rate limitu (null = pomiń sprawdzenie, np. w testach)
     */
    public function register(string $name, string $email, string $password, ?string $ip = null): User
    {
        // ZAD-2.4 (P4): rate limit chroni przed spam-rejestracją (sprawdzony PRZED walidacją).
        // Brak tego wcześniej pozwalał botom tworzyć tysiące kont + rozsyłać zaproszenia
        // (amplifikacja SMTP, reputation damage).
        if ($ip !== null && $this->isRateLimited($ip, 'register')) {
            throw new \RuntimeException('Zbyt wiele prób rejestracji. Spróbuj za 15 minut.');
        }

        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new \InvalidArgumentException('Imię musi mieć od 2 do 100 znaków.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Nieprawidłowy adres e-mail.');
        }
        $this->validatePasswordStrength($password);
        if ($this->userRepo->emailExists($email)) {
            // Loguj próbę żeby attacker nie mógł użyć /register jako enumeration
            if ($ip !== null) {
                $this->recordAttempt($ip, 'register');
            }
            throw new \InvalidArgumentException('Konto z tym adresem e-mail już istnieje.');
        }

        $id   = Uuid::generate();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // ZAD-1.1 (K1): RODO Art. 7(1) — zapisz wersję regulaminu akceptowaną
        // przy rejestracji. Umożliwia udowodnienie zgody + wyłapanie userów
        // z nieaktualną wersją (np. do re-acceptance prompt).
        $termsVersion = defined('TERMS_VERSION') ? TERMS_VERSION : '2026-04-08';
        $this->userRepo->create($id, $email, $hash, $name, 'pl', $termsVersion);

        if ($ip !== null) {
            $this->recordAttempt($ip, 'register');
        }

        return $this->userRepo->findById($id)
            ?? throw new \RuntimeException('Błąd tworzenia konta.');
    }

    public function login(string $email, string $password, string $ip): User
    {
        // Rate limit sprawdz PRZED weryfikacją hasła
        if ($this->isRateLimited($ip, 'login')) {
            throw new \RuntimeException('Zbyt wiele prób logowania. Spróbuj za 15 minut.');
        }

        $row = $this->userRepo->findByEmailWithHash($email);

        if (!$row || !password_verify($password, $row['password_hash'])) {
            $this->recordAttempt($ip, 'login');
            throw new \InvalidArgumentException('Nieprawidłowy e-mail lub hasło.');
        }

        return User::fromArray($row);
    }

    /**
     * ZAD-3.2 (P11): wycofanie zawieszenia konta (RODO Art. 18).
     * Wywoływane przez AuthController::processLogin po udanym logowaniu na zawieszonym koncie.
     */
    public function clearRestriction(string $userId): void
    {
        $this->userRepo->setRestricted($userId, false);
    }

    /**
     * Walidacja siły hasła zgodnie z NIST SP 800-63B (audit D1):
     * - Minimum 12 znaków
     * - Co najmniej jedna cyfra LUB znak specjalny (entropy boost)
     */
    public function validatePasswordStrength(string $password): void
    {
        if (strlen($password) < 12) {
            throw new \InvalidArgumentException('Hasło musi mieć co najmniej 12 znaków.');
        }
        if (!preg_match('/[0-9]/', $password) && !preg_match('/[^A-Za-z0-9]/', $password)) {
            throw new \InvalidArgumentException('Hasło musi zawierać co najmniej jedną cyfrę lub znak specjalny.');
        }
    }

    /**
     * Backward-compat — wewnątrz deleguje do `RateLimiter` (jeśli wstrzyknięty).
     * Zachowuje stary API używany przez testy.
     */
    public function isRateLimited(string $ip, string $endpoint): bool
    {
        if ($this->rateLimiter !== null) {
            return $this->rateLimiter->isLimited($ip, $endpoint, RATE_LIMIT_ATTEMPTS, RATE_LIMIT_WINDOW);
        }
        // Fallback gdy RateLimiter nie wstrzyknięty (np. testy unit)
        $row = $this->db->fetchOne(
            'SELECT SUM(attempts) as total FROM rate_limits
             WHERE ip = :ip AND endpoint = :endpoint
             AND window_start > DATE_SUB(NOW(), INTERVAL :window SECOND)',
            [':ip' => $ip, ':endpoint' => $endpoint, ':window' => RATE_LIMIT_WINDOW]
        );
        return (int)($row['total'] ?? 0) >= RATE_LIMIT_ATTEMPTS;
    }

    public function recordAttempt(string $ip, string $endpoint): void
    {
        if ($this->rateLimiter !== null) {
            $this->rateLimiter->record($ip, $endpoint);
            return;
        }
        $this->db->execute(
            'INSERT INTO rate_limits (ip, endpoint, attempts) VALUES (:ip, :endpoint, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1',
            [':ip' => $ip, ':endpoint' => $endpoint]
        );
    }

}
