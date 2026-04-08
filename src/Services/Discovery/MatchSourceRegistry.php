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

    public function register(MatchSourceInterface $source): void
    {
        $this->sources[$source->getName()] = $source;
    }

    public function get(string $name): ?MatchSourceInterface
    {
        return $this->sources[$name] ?? null;
    }

    /**
     * Zwraca tylko te źródła które są aktywne (isAvailable() == true).
     *
     * @return list<MatchSourceInterface>
     */
    public function getEnabled(): array
    {
        $enabled = [];
        foreach ($this->sources as $source) {
            if ($source->isAvailable()) {
                $enabled[] = $source;
            }
        }
        return $enabled;
    }
}
