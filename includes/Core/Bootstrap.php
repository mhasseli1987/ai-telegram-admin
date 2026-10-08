<?php
namespace ATA;

defined('ABSPATH') || exit;

/**
 * Service Container — minimal DI (D-13).
 * Maps interface → factory. Lazy. No external framework.
 */
class Container
{
    private static ?Container $instance = null;
    private array $bindings = [];
    private array $singletons = [];

    public static function instance(): Container
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function bind(string $abstract, callable $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable $concrete): void
    {
        $this->singletons[$abstract] = $concrete;
    }

    public function make(string $abstract)
    {
        if (isset($this->singletons[$abstract])) {
            static $resolved = [];
            if (!isset($resolved[$abstract])) {
                $resolved[$abstract] = call_user_func($this->singletons[$abstract], $this);
            }
            return $resolved[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return call_user_func($this->bindings[$abstract], $this);
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
    }
}