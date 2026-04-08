<?php
declare(strict_types=1);

namespace App\Services\Discovery\DTO;

/**
 * Kontekst wyszukiwania — kto szuka, gdzie, jakie źródła włączyć.
 */
final class SearchContext
{
    /**
     * @param list<string> $accessibleTreeIds lista drzew do których user ma dostęp
     *                                        (owner LUB editor LUB viewer przez tree_members)
     */
    public function __construct(
        public readonly string $currentUserId,
        public readonly string $currentTreeId,
        public readonly array  $accessibleTreeIds,
        public readonly bool   $includeLocal     = true,
        public readonly bool   $includeCrossTree = true,
        public readonly bool   $includeExternal  = false,
    ) {}
}
