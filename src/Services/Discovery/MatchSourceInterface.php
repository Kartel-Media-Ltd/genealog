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
    // ZAD-3.6 (D6): stałe dla nazw źródeł — eliminuje magic strings w MatchingService.
    public const SOURCE_LOCAL      = 'local';
    public const SOURCE_CROSS_TREE = 'cross_tree';
    public const SOURCE_EXTERNAL   = 'external';

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

    /**
     * ZAD-2.5 (P5): timeout w sekundach dla wywołania search().
     *
     * Local/internal sources: 5s (default).
     * External HTTP sources: 10-15s (FamilySearch API, Geneteka scraper).
     *
     * Używane przez MatchingService + implementacje external sources żeby
     * wymusić timeout (curl CURLOPT_TIMEOUT lub stream_context timeout).
     * Eliminuje sytuację gdzie zawieszona external source blokuje cały request usera.
     */
    public function getTimeoutSeconds(): int;
}
