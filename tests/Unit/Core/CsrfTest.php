<?php
declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        // Uruchom sesję w trybie testowym (bez wysyłania headerów)
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Wyczyść token przed każdym testem
        unset($_SESSION['_csrf_token']);
    }

    public function testGenerateReturns64CharHexString(): void
    {
        $token = Csrf::generate();

        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testGenerateStoresTokenInSession(): void
    {
        $token = Csrf::generate();

        $this->assertSame($token, $_SESSION['_csrf_token']);
    }

    public function testVerifyValidToken(): void
    {
        $token = Csrf::generate();

        $this->assertTrue(Csrf::verify($token));
    }

    public function testVerifyInvalidToken(): void
    {
        Csrf::generate();

        $this->assertFalse(Csrf::verify('invalid_token'));
    }

    public function testVerifyEmptyToken(): void
    {
        Csrf::generate();

        $this->assertFalse(Csrf::verify(''));
    }

    public function testVerifyWithoutGeneratedToken(): void
    {
        // Brak tokenu w sesji — weryfikacja powinna zwrócić false
        $this->assertFalse(Csrf::verify('any_token'));
    }

    public function testHiddenInputContainsToken(): void
    {
        $token = Csrf::generate();
        $html  = Csrf::hiddenInput();

        $this->assertStringContainsString('type="hidden"', $html);
        $this->assertStringContainsString('name="_csrf_token"', $html);
        $this->assertStringContainsString($token, $html);
    }
}
