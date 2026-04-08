<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Prosty in-process event dispatcher.
 *
 * Używany do luźnego sprzęgania serwisów — np. po `PersonService::create()`
 * emitujemy `person.created` i wszystkie zarejestrowane listenery reagują
 * (GlobalIndexService, MatchingService, audit log, etc.) bez modyfikacji
 * kodu serwisu źródłowego.
 *
 * Uwaga: listenery wywoływane są synchronicznie w tym samym request. Wyjątki
 * z listenerów są logowane i pochłaniane — jeden nieudany listener nie
 * blokuje operacji głównej ani pozostałych listenerów.
 */
final class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private static array $listeners = [];

    /**
     * Zarejestruj listener dla eventu.
     */
    public static function on(string $event, callable $listener): void
    {
        self::$listeners[$event][] = $listener;
    }

    /**
     * Wyemituj event. Argumenty są przekazywane do listenerów.
     * Wyjątki z listenerów są logowane, ale nie propagowane — operacja
     * wywołująca emit() nie powinna się zawalić przez awarię listenera.
     */
    public static function emit(string $event, mixed ...$args): void
    {
        $listeners = self::$listeners[$event] ?? [];
        foreach ($listeners as $listener) {
            try {
                $listener(...$args);
            } catch (\Throwable $e) {
                error_log(sprintf(
                    '[EventDispatcher] listener for "%s" failed: %s in %s:%d',
                    $event,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                ));
            }
        }
    }

    /**
     * Wyczyść wszystkie listenery dla danego eventu (lub wszystkie jeśli brak argumentu).
     * Używane w testach żeby nie dopuścić do wycieku stanu między przypadkami.
     */
    public static function clear(?string $event = null): void
    {
        if ($event === null) {
            self::$listeners = [];
            return;
        }
        unset(self::$listeners[$event]);
    }
}
