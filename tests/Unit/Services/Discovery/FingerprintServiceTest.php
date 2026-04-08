<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Discovery;

use App\Services\Discovery\FingerprintService;
use PHPUnit\Framework\TestCase;

class FingerprintServiceTest extends TestCase
{
    private FingerprintService $service;

    protected function setUp(): void
    {
        $this->service = new FingerprintService();
    }

    // ─── compute() ───────────────────────────────────────────────────────────

    public function testComputeReturnsHashForFullData(): void
    {
        $hash = $this->service->compute('Jan', 'Kowalski', 1900, 'Warszawa, mazowieckie');
        $this->assertNotNull($hash);
        $this->assertSame(64, strlen($hash)); // SHA-256 = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $hash);
    }

    public function testComputeIsDeterministic(): void
    {
        $hash1 = $this->service->compute('Jan', 'Kowalski', 1900, 'Warszawa, mazowieckie');
        $hash2 = $this->service->compute('Jan', 'Kowalski', 1900, 'Warszawa, mazowieckie');
        $this->assertSame($hash1, $hash2);
    }

    public function testComputeIsCaseInsensitive(): void
    {
        $hash1 = $this->service->compute('Jan', 'Kowalski', 1900, 'Warszawa');
        $hash2 = $this->service->compute('JAN', 'KOWALSKI', 1900, 'warszawa');
        $this->assertSame($hash1, $hash2);
    }

    public function testComputeNormalizesPolishDiacritics(): void
    {
        // "Łukasz Kowalski" i "Lukasz Kowalski" powinny dać ten sam hash
        // bo transliteracja usuwa diakrytyki
        $withDiacritic    = $this->service->compute('Łukasz', 'Kowalski', 1900, null);
        $withoutDiacritic = $this->service->compute('Lukasz', 'Kowalski', 1900, null);
        $this->assertSame($withDiacritic, $withoutDiacritic);
    }

    public function testComputeReturnsNullWhenFirstNameMissing(): void
    {
        $this->assertNull($this->service->compute(null, 'Kowalski', 1900, null));
        $this->assertNull($this->service->compute('', 'Kowalski', 1900, null));
    }

    public function testComputeReturnsNullWhenLastNameMissing(): void
    {
        $this->assertNull($this->service->compute('Jan', null, 1900, null));
        $this->assertNull($this->service->compute('Jan', '', 1900, null));
    }

    public function testComputeWorksWithoutBirthYearAndPlace(): void
    {
        $hash = $this->service->compute('Jan', 'Kowalski', null, null);
        $this->assertNotNull($hash);
        $this->assertSame(64, strlen($hash));
    }

    public function testComputeDifferentForDifferentPersons(): void
    {
        $hash1 = $this->service->compute('Jan', 'Kowalski', 1900, 'Warszawa');
        $hash2 = $this->service->compute('Jan', 'Nowak', 1900, 'Warszawa');
        $this->assertNotSame($hash1, $hash2);
    }

    // ─── computeSoundex() ────────────────────────────────────────────────────

    public function testSoundexReturnsCodeForName(): void
    {
        $soundex = $this->service->computeSoundex('Jan', 'Kowalski');
        $this->assertNotNull($soundex);
        $this->assertNotEmpty($soundex);
    }

    public function testSoundexReturnsNullForEmptyLastName(): void
    {
        $this->assertNull($this->service->computeSoundex('Jan', null));
        $this->assertNull($this->service->computeSoundex('Jan', ''));
    }

    public function testSoundexHandlesPolishDiacritics(): void
    {
        // Soundex z diakrytykami i bez powinien dać ten sam wynik (po transliteracji)
        $sdx1 = $this->service->computeSoundex('Łukasz', 'Kowalski');
        $sdx2 = $this->service->computeSoundex('Lukasz', 'Kowalski');
        $this->assertSame($sdx1, $sdx2);
    }

    public function testSoundexLengthAtMost8Chars(): void
    {
        $sdx = $this->service->computeSoundex('Jan', 'Kowalski');
        $this->assertNotNull($sdx);
        $this->assertLessThanOrEqual(8, strlen($sdx));
    }

    // ─── isHistorical() ──────────────────────────────────────────────────────

    public function testIsHistoricalReturnsTrueForOldPerson(): void
    {
        $oldYear = (int)date('Y') - 150;
        $this->assertTrue($this->service->isHistorical(false, $oldYear));
    }

    public function testIsHistoricalReturnsFalseForLivingPerson(): void
    {
        $oldYear = (int)date('Y') - 150;
        $this->assertFalse($this->service->isHistorical(true, $oldYear));
    }

    public function testIsHistoricalReturnsFalseForRecentPerson(): void
    {
        $recentYear = (int)date('Y') - 50;
        $this->assertFalse($this->service->isHistorical(false, $recentYear));
    }

    public function testIsHistoricalReturnsTrueWhenYearMissing(): void
    {
        // Zmienione w fixie GlobalIndexService: niezyjąca osoba bez daty urodzenia
        // traktowana jako historyczna (umożliwia indeksację globalną).
        $this->assertTrue($this->service->isHistorical(false, null));
    }

    public function testIsHistoricalBoundaryAtExactly100Years(): void
    {
        $year100 = (int)date('Y') - 100;
        // < 100 lat ago → false (osoba mogłaby jeszcze żyć)
        $this->assertFalse($this->service->isHistorical(false, $year100));

        $year101 = (int)date('Y') - 101;
        $this->assertTrue($this->service->isHistorical(false, $year101));
    }

    // ─── extractRegion() ─────────────────────────────────────────────────────

    public function testExtractRegionFromCommaSeparated(): void
    {
        $this->assertSame(
            'mazowieckie',
            $this->service->extractRegion('Warszawa, mazowieckie')
        );
    }

    public function testExtractRegionFallbacksToWholeWhenNoComma(): void
    {
        $this->assertSame('kraków', $this->service->extractRegion('Kraków'));
    }

    public function testExtractRegionReturnsNullForEmpty(): void
    {
        $this->assertNull($this->service->extractRegion(null));
        $this->assertNull($this->service->extractRegion(''));
        $this->assertNull($this->service->extractRegion('   '));
    }

    public function testExtractRegionHandlesMultipleCommas(): void
    {
        // Bierzemy ostatnią część po przecinku
        $this->assertSame(
            'pomorskie',
            $this->service->extractRegion('Gdańsk, woj. pomorskie, Polska, pomorskie')
        );
    }

    public function testExtractRegionLowercase(): void
    {
        $this->assertSame(
            'mazowieckie',
            $this->service->extractRegion('Warszawa, MAZOWIECKIE')
        );
    }

    // ─── asciiNormalize() ────────────────────────────────────────────────────

    public function testAsciiNormalizeRemovesDiacritics(): void
    {
        // Najpopularniejsze polskie diakrytyki — testujemy te pewne dla iconv
        $this->assertSame('lukasz', $this->service->asciiNormalize('Łukasz'));
        $this->assertSame('jaroslaw', $this->service->asciiNormalize('Jarosław'));
        // ą/ę → a/e
        $this->assertSame('jadwiga', $this->service->asciiNormalize('Jądwiga'));
    }

    public function testAsciiNormalizeLowercases(): void
    {
        $this->assertSame('kowalski', $this->service->asciiNormalize('KOWALSKI'));
    }
}
