<?php
declare(strict_types=1);

namespace App\Services\Discovery\Sources;

use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\Discovery\MatchSourceInterface;

/**
 * Stub adaptera dla Geneteki (PTG) — wyszukiwanie w lokalnej kopii indeksów
 * albo przez scraper HTTP.
 *
 * Pełna implementacja w `dev/active/registries/` (faza 1 tego planu):
 *   - GenetykaService::search() zwraca array z polami surname, given_name,
 *     birth_year, parish, region, document_type, year, etc.
 *   - Ten adapter transformuje te wyniki na `MatchResult[]` z sourceType='external'.
 *
 * Zarejestrowany warunkowo w `public/index.php`:
 *     if (getenv('GENETEKA_LOCAL_DB')) {
 *         $matchRegistry->register(new GenetykaMatchSource(...));
 *     }
 */
final class GenetykaMatchSource implements MatchSourceInterface
{
    private readonly ?string $localDbPath;

    public function __construct(?string $localDbPath = null)
    {
        // ZAD-2.11 (P11): walidacja path żeby uniknąć path traversal przy pełnej implementacji.
        // Dopuszczalne są tylko ścieżki w STORAGE_PATH/geneteka/ — wszystko inne jest
        // ignorowane (source zostaje wyłączona przez isAvailable() → false).
        if ($localDbPath === null || $localDbPath === '') {
            $this->localDbPath = null;
            return;
        }

        $real = realpath($localDbPath);
        $storageRoot = defined('STORAGE_PATH')
            ? STORAGE_PATH
            : dirname(__DIR__, 4) . '/storage';
        $allowed = realpath($storageRoot . '/geneteka');

        if ($real === false
            || $allowed === false
            || (!str_starts_with($real, $allowed . DIRECTORY_SEPARATOR) && $real !== $allowed)
        ) {
            error_log('[GenetykaMatchSource] invalid or unauthorized path — source disabled: ' . $localDbPath);
            $this->localDbPath = null;
            return;
        }

        $this->localDbPath = $real;
    }

    public function getName(): string
    {
        return 'geneteka';
    }

    public function isAvailable(): bool
    {
        return $this->localDbPath !== null && is_readable($this->localDbPath);
    }

    public function getTimeoutSeconds(): int
    {
        // Geneteka to lokalne pliki CSV/SQLite — szybsze niż HTTP, ale wolniejsze niż in-memory DB
        return 8;
    }

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        // TODO: wywołać GenetykaService::search($criteria->toArray()) z planu registries,
        // transformować wyniki do MatchResult[] z sourceType='external' i confidence
        // bazowaną na match_score z rejestru (fallback 0.6).
        // UWAGA (ZAD-2.7 P7): przy implementacji dodaj audit log (Art. 30) przed return.
        return [];
    }
}
