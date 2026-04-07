<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Bardzo prosty DI Container — tylko dla MVP.
 *
 * Wzorzec: Service Locator + lazy loading.
 * Zamiast 90+ linii konstrukcji w `public/index.php`, definiuj zależności
 * jako fabryki (closures) i pobieraj przez `Container::get(ClassName::class)`.
 *
 * Ograniczenia:
 * - Brak autowire (musisz zarejestrować każdą zależność ręcznie)
 * - Brak rozpoznawania typów (nie czyta argumentów konstruktora przez Reflection)
 * - Singleton scope (każda fabryka wywoływana raz, wynik zwracany przy kolejnych get())
 *
 * Dla MVP wystarczy. Później można podmienić na PHP-DI lub Laminas.
 *
 * @example
 *   $c = new Container();
 *   $c->set(Database::class, fn() => Database::getInstance());
 *   $c->set(UserRepository::class, fn(Container $c) => new UserRepository($c->get(Database::class)));
 *   $userRepo = $c->get(UserRepository::class);
 */
class Container
{
    /** @var array<string, \Closure> */
    private array $factories = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    /**
     * Zarejestruj fabrykę dla klasy/interfejsu.
     *
     * @param string   $id      Identyfikator (zwykle FQCN)
     * @param \Closure $factory `function(Container $c) { return new MyService(...); }`
     */
    public function set(string $id, \Closure $factory): void
    {
        $this->factories[$id] = $factory;
        // Wyczyść cached instance gdyby była nadpisywana
        unset($this->instances[$id]);
    }

    /**
     * Pobierz instancję — fabryka jest wywoływana raz, wynik cachowany.
     */
    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new \RuntimeException("Container: no factory registered for '{$id}'");
        }

        $instance = ($this->factories[$id])($this);
        $this->instances[$id] = $instance;
        return $instance;
    }

    public function has(string $id): bool
    {
        return isset($this->factories[$id]) || isset($this->instances[$id]);
    }

    /** Zarejestruj gotową instancję (np. obiekt zewnętrzny). */
    public function instance(string $id, mixed $instance): void
    {
        $this->instances[$id] = $instance;
    }
}
