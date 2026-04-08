<?php
declare(strict_types=1);

namespace App\Services\Discovery\Sources;

use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;
use App\Services\Discovery\MatchSourceInterface;

/**
 * Stub adaptera dla FamilySearch API.
 *
 * Pełna implementacja wymaga wcześniejszego wdrożenia `FamilySearchService`
 * z planu `dev/active/registries/` (OAuth2, REST API, sandbox/prod).
 *
 * Ten stub jest zarejestrowany warunkowo w `public/index.php`:
 *     if (getenv('FAMILYSEARCH_CLIENT_ID')) {
 *         $matchRegistry->register(new FamilySearchMatchSource(...));
 *     }
 *
 * Dopóki client_id nie jest skonfigurowany, `isAvailable()` zwraca false
 * i source jest ignorowany przez MatchSourceRegistry::getEnabled().
 */
final class FamilySearchMatchSource implements MatchSourceInterface
{
    /**
     * W pełnej implementacji konstruktor przyjmuje `FamilySearchService $service`
     * z planu registries. Na etapie stuba zostawiam pusty — wystarczy do
     * rejestracji w registry.
     */
    public function __construct(
        private readonly ?string $clientId = null,
    ) {}

    public function getName(): string
    {
        return 'familysearch';
    }

    public function isAvailable(): bool
    {
        return $this->clientId !== null && $this->clientId !== '';
    }

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array
    {
        // TODO: wywołać FamilySearchService::search($criteria) z planu registries
        // i transformować wyniki do MatchResult[] z sourceType='external'.
        return [];
    }
}
