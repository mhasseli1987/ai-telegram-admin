<?php
namespace ATA\Core;

defined('ABSPATH') || exit;

/**
 * Service Container — minimal DI (D-13).
 * Maps interface/class → factory. Lazy. No external framework.
 *
 * Re-registration semantics: calling bind()/singleton() for an already-known
 * abstract replaces the previous factory and drops any cached instance, so
 * "last registration wins" (used by tests to swap the HTTP transport).
 */
class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $singletons = [];
    private array $resolved = [];

    public static function instance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, callable $concrete): void
    {
        unset($this->singletons[$abstract], $this->resolved[$abstract]);
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        unset($this->bindings[$abstract], $this->resolved[$abstract]);
        $this->singletons[$abstract] = $concrete;
    }

    public function make(string $abstract)
    {
        if (isset($this->singletons[$abstract])) {
            if (!array_key_exists($abstract, $this->resolved)) {
                $this->resolved[$abstract] = call_user_func($this->singletons[$abstract], $this);
            }
            return $this->resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return call_user_func($this->bindings[$abstract], $this);
        }

        if (interface_exists($abstract)) {
            // Never `new` an interface — it fatals with a confusing error.
            throw new \RuntimeException("Interface has no binding: {$abstract}");
        }

        if (class_exists($abstract)) {
            return new $abstract();
        }

        throw new \RuntimeException("Unresolved binding: {$abstract}");
    }

    public function reset(): void
    {
        $this->bindings = [];
        $this->singletons = [];
        $this->resolved = [];
    }
}
