<?php
declare(strict_types=1);

namespace App\Services\Discovery;

/**
 * DI container dla implementacji `MatchSourceInterface`.
 * Dodanie nowego źródła = jedna linia `register()` w `public/index.php`.
 */
final class MatchSourceRegistry
{
    /** @var array<string, MatchSourceInterface> */
    private array $sources = [];

    /**
     * ZAD-3.4 (D4): cache dla `getEnabled()` — per-request memoization.
     * Gdy external sources zaimplementują health-check HTTP w `isAvailable()`,
     * brak cache oznaczałby osobny request HTTP przy KAŻDYM search.
     * Invalidated przy `register()`.
     *
     * @var list<MatchSourceInterface>|null
     */
    private ?array $enabledCache = null;

    public function register(MatchSourceInterface $source): void
    {
        $this->sources[$source->getName()] = $source;
        $this->enabledCache = null; // invalidate
    }

    public function get(string $name): ?MatchSourceInterface
    {
        return $this->sources[$name] ?? null;
    }

    /**
     * Zwraca tylko te źródła które są aktywne (isAvailable() == true).
     * Wynik jest cachowany per instance (nie per request) — MatchSourceRegistry
     * jest singleton w bootstrap, więc de facto to cache per request.
     *
     * @return list<MatchSourceInterface>
     */
    public function getEnabled(): array
    {
        if ($this->enabledCache !== null) {
            return $this->enabledCache;
        }
        $enabled = [];
        foreach ($this->sources as $source) {
            if ($source->isAvailable()) {
                $enabled[] = $source;
            }
        }
        return $this->enabledCache = $enabled;
    }
}
