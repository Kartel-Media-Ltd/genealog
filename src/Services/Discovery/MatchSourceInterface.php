<?php
declare(strict_types=1);

namespace App\Services\Discovery;

use App\Services\Discovery\DTO\MatchResult;
use App\Services\Discovery\DTO\SearchContext;
use App\Services\Discovery\DTO\SearchCriteria;

/**
 * Abstrakcja źródła dopasowań osób.
 *
 * Każde źródło (lokalne drzewa, cross-tree przez global_person_index,
 * external registries jak FamilySearch) implementuje ten interfejs
 * i jest rejestrowane w `MatchSourceRegistry`. Dodanie nowego źródła
 * nie wymaga modyfikacji `MatchingService` — wystarczy register().
 */
interface MatchSourceInterface
{
    /**
     * Unikalna nazwa źródła. Używana jako klucz w registry i w odpowiedzi JSON.
     * Np. 'local', 'cross_tree', 'familysearch', 'geneteka'.
     */
    public function getName(): string;

    /**
     * @return list<MatchResult>
     */
    public function search(SearchCriteria $criteria, SearchContext $context): array;

    /**
     * Czy źródło jest aktywne (np. env var skonfigurowana, API dostępne)?
     * Jeśli false → MatchSourceRegistry::getEnabled() pomija.
     */
    public function isAvailable(): bool;
}
