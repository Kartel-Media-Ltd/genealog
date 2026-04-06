<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly Database       $db,
    ) {}

    public function register(string $name, string $email, string $password): User
    {
        if (strlen($name) < 2 || strlen($name) > 100) {
            throw new \InvalidArgumentException('Imię musi mieć od 2 do 100 znaków.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Nieprawidłowy adres e-mail.');
        }
        if (strlen($password) < 8) {
            throw new \InvalidArgumentException('Hasło musi mieć co najmniej 8 znaków.');
        }
        if ($this->userRepo->emailExists($email)) {
            throw new \InvalidArgumentException('Konto z tym adresem e-mail już istnieje.');
        }

        $id   = $this->generateUuid();
        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        $this->userRepo->create($id, $email, $hash, $name);

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

    public function isRateLimited(string $ip, string $endpoint): bool
    {
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
        $this->db->execute(
            'INSERT INTO rate_limits (ip, endpoint, attempts) VALUES (:ip, :endpoint, 1)
             ON DUPLICATE KEY UPDATE attempts = attempts + 1',
            [':ip' => $ip, ':endpoint' => $endpoint]
        );
    }

    private function generateUuid(): string
    {
        $data    = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
