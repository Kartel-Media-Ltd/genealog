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
    public function __construct(
        private readonly ?string $localDbPath = null,
    ) {}

    public function getName(): string
    {
        return 'geneteka';
    }

    public function isAvailable(): bool
    {
        return $this->localDbPath !== null && $this->localDbPath !== '';
    }

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        // TODO: wywołać GenetykaService::search($criteria->toArray()) z planu registries,
        // transformować wyniki do MatchResult[] z sourceType='external' i confidence
        // bazowaną na match_score z rejestru (fallback 0.6).
        return [];
    }
}
