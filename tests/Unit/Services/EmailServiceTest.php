<?php
declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\EmailService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * ZAD-3.12 (D12) — unit testy dla EmailService::sanitizeHeader.
 *
 * KRYTYCZNE: chroni K1 fix z backend-3 (SMTP header injection).
 * Każda regresja w `sanitizeHeader` = realna podatność CVE.
 */
final class EmailServiceTest extends TestCase
{
    private EmailService $service;
    private ReflectionMethod $sanitize;

    protected function setUp(): void
    {
        $this->service = new EmailService();
        $this->sanitize = new ReflectionMethod($this->service, 'sanitizeHeader');
        $this->sanitize->setAccessible(true);
    }

    public function testSanitizeHeaderRemovesCR(): void
    {
        $this->assertSame('JanKowalski', $this->sanitize->invoke($this->service, "Jan\rKowalski"));
    }

    public function testSanitizeHeaderRemovesLF(): void
    {
        $this->assertSame('JanKowalski', $this->sanitize->invoke($this->service, "Jan\nKowalski"));
    }

    public function testSanitizeHeaderRemovesCRLF(): void
    {
        $this->assertSame('JanKowalski', $this->sanitize->invoke($this->service, "Jan\r\nKowalski"));
    }

    public function testSanitizeHeaderRemovesNullByte(): void
    {
        $this->assertSame('JanNull', $this->sanitize->invoke($this->service, "Jan\0Null"));
    }

    public function testSanitizeHeaderPreventsBccInjection(): void
    {
        $malicious = "Jan Kowalski\r\nBcc: attacker@evil.com";
        $sanitized = $this->sanitize->invoke($this->service, $malicious);

        // CR i LF powinny być usunięte
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertStringNotContainsString("\n", $sanitized);

        // "Bcc:" jako sam string może pozostać (to nie niebezpieczne samo w sobie),
        // ale bez \r\n nie ma możliwości wstrzyknięcia nagłówka
        $this->assertSame('Jan KowalskiBcc: attacker@evil.com', $sanitized);
    }

    public function testSanitizeHeaderPreventsMultipleInjections(): void
    {
        $payload = "Jan\r\nBcc: a@evil.com\r\nSubject: Spam\r\nX-Custom: bad";
        $sanitized = $this->sanitize->invoke($this->service, $payload);
        $this->assertStringNotContainsString("\r", $sanitized);
        $this->assertStringNotContainsString("\n", $sanitized);
    }

    public function testSanitizeHeaderPreservesCleanString(): void
    {
        $clean = 'Jan Kowalski';
        $this->assertSame($clean, $this->sanitize->invoke($this->service, $clean));
    }

    public function testSanitizeHeaderPreservesUnicodeCharacters(): void
    {
        // Polskie znaki diakrytyczne nie powinny być modyfikowane
        $name = 'Łukasz Gąsior';
        $this->assertSame($name, $this->sanitize->invoke($this->service, $name));
    }

    public function testSanitizeHeaderHandlesEmptyString(): void
    {
        $this->assertSame('', $this->sanitize->invoke($this->service, ''));
    }

    public function testSanitizeHeaderHandlesOnlyWhitespaceInjection(): void
    {
        $this->assertSame('', $this->sanitize->invoke($this->service, "\r\n\0"));
    }

    public function testSanitizeHeaderPreservesTabCharacter(): void
    {
        // Tab NIE jest w whitelist usuwania — sanitizeHeader celowo usuwa tylko CR/LF/NULL.
        // Ten test dokumentuje intended behavior: tab nie jest niebezpieczny w kontekście
        // SMTP header injection (tylko CR/LF rozdziela nagłówki RFC 5322).
        $result = $this->sanitize->invoke($this->service, "Jan\tKowalski");
        $this->assertSame("Jan\tKowalski", $result);
    }
}
