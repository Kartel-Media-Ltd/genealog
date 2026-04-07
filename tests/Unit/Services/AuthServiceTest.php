<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\Database;
use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\AuthService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private UserRepository&MockObject $userRepo;
    private Database&MockObject       $db;
    private AuthService               $authService;

    protected function setUp(): void
    {
        $this->userRepo    = $this->createMock(UserRepository::class);
        $this->db          = $this->createMock(Database::class);
        $this->authService = new AuthService($this->userRepo, $this->db);
    }

    // ─── register ────────────────────────────────────────────────────────────

    public function testRegisterSuccess(): void
    {
        $this->userRepo->expects($this->once())
            ->method('emailExists')
            ->willReturn(false);

        $this->userRepo->expects($this->once())
            ->method('create');

        $expectedUser = new User(
            id:                 'uuid-1',
            email:              'jan@test.pl',
            name:               'Jan',
            locale:             'pl',
            isActive:           true,
            emailNotifications: true,
            isAdmin:            false,
            isBlocked:          false,
            createdAt:          '2026-01-01',
        );
        $this->userRepo->expects($this->once())
            ->method('findById')
            ->willReturn($expectedUser);

        $user = $this->authService->register('Jan Kowalski', 'jan@test.pl', 'password123');

        $this->assertSame('jan@test.pl', $user->email);
    }

    public function testRegisterThrowsOnShortName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Imię musi mieć od 2 do 100 znaków');

        $this->authService->register('J', 'jan@test.pl', 'password123');
    }

    public function testRegisterThrowsOnInvalidEmail(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nieprawidłowy adres e-mail');

        $this->authService->register('Jan', 'not-an-email', 'password123');
    }

    public function testRegisterThrowsOnShortPassword(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Hasło musi mieć co najmniej 8 znaków');

        $this->authService->register('Jan', 'jan@test.pl', 'short');
    }

    public function testRegisterThrowsOnDuplicateEmail(): void
    {
        $this->userRepo->method('emailExists')->willReturn(true);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Konto z tym adresem e-mail już istnieje');

        $this->authService->register('Jan', 'jan@test.pl', 'password123');
    }

    // ─── login ───────────────────────────────────────────────────────────────

    public function testLoginSuccess(): void
    {
        $hash = password_hash('password123', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        // Brak rate limitów
        $this->db->method('fetchOne')
            ->willReturn(['total' => 0]);

        $row = [
            'id' => 'uuid-1', 'email' => 'jan@test.pl', 'name' => 'Jan',
            'password_hash' => $hash, 'locale' => 'pl', 'is_active' => 1, 'created_at' => '2026-01-01',
        ];
        $this->userRepo->method('findByEmailWithHash')->willReturn($row);

        $user = $this->authService->login('jan@test.pl', 'password123', '127.0.0.1');

        $this->assertSame('jan@test.pl', $user->email);
    }

    public function testLoginThrowsOnWrongPassword(): void
    {
        $hash = password_hash('correct_pass', PASSWORD_BCRYPT, ['cost' => BCRYPT_COST]);

        $this->db->method('fetchOne')->willReturn(['total' => 0]);

        $row = [
            'id' => 'uuid-1', 'email' => 'jan@test.pl', 'name' => 'Jan',
            'password_hash' => $hash, 'locale' => 'pl', 'is_active' => 1, 'created_at' => '2026-01-01',
        ];
        $this->userRepo->method('findByEmailWithHash')->willReturn($row);
        $this->db->method('execute'); // recordAttempt

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Nieprawidłowy e-mail lub hasło');

        $this->authService->login('jan@test.pl', 'wrong_pass', '127.0.0.1');
    }

    public function testLoginThrowsWhenRateLimited(): void
    {
        $this->db->method('fetchOne')
            ->willReturn(['total' => RATE_LIMIT_ATTEMPTS]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Zbyt wiele prób logowania');

        $this->authService->login('jan@test.pl', 'password123', '127.0.0.1');
    }

    public function testLoginThrowsOnUnknownEmail(): void
    {
        $this->db->method('fetchOne')->willReturn(['total' => 0]);
        $this->userRepo->method('findByEmailWithHash')->willReturn(null);
        $this->db->method('execute'); // recordAttempt

        $this->expectException(\InvalidArgumentException::class);

        $this->authService->login('unknown@test.pl', 'password123', '127.0.0.1');
    }
}
