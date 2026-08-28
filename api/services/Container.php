<?php

namespace App\API\Services;

use App\Database\Database;
use PDO;
use ReflectionClass;
use ReflectionNamedType;
use RuntimeException;

/**
 * Container - lightweight dependency-injection container (framework-free).
 *
 * Borrows Laravel's service-container ideas without the framework:
 *   - singleton(): reuse one instance across the request.
 *   - bind(): factory producing a new instance each resolution.
 *   - instance(): store a pre-built instance.
 *   - make()/get(): resolve a class, auto-wiring constructor dependencies.
 *
 * Auto-wiring rules (in priority order) for a constructor parameter:
 *   1. An explicit binding / singleton registered under the param's type.
 *   2. The shared PDO database connection (for `PDO` typed params).
 *   3. A reflectively resolvable class type (recursively built).
 *
 * This replaces scattered `new Service(...)` in controllers with one
 * composition root (config/bootstrap) and lets tests swap fakes via
 * bind()/instance().
 */
class Container
{
    /** @var array<string, mixed> Instances (singletons / bound instances). */
    private static $instances = [];

    /** @var array<string, string|callable> Bindings abstract => concrete. */
    private static $bindings = [];

    /** @var array<string, callable> Singletons abstract => factory. */
    private static $singletons = [];

    /** @var string|null Cached composition root marker. */
    private static $booted = false;

    /**
     * Register a singleton (resolved once, cached for the request).
     *
     * @param string $abstract
     * @param string|callable|null $concrete Concrete class name or factory,
     *                                         or null to resolve $abstract.
     */
    public static function singleton(string $abstract, $concrete = null): void
    {
        self::$singletons[$abstract] = $concrete === null ? $abstract : $concrete;
    }

    /**
     * Register a transient binding.
     */
    public static function bind(string $abstract, $concrete): void
    {
        self::$bindings[$abstract] = $concrete;
    }

    /**
     * Store a resolved instance under an abstract.
     */
    public static function instance(string $abstract, $instance): void
    {
        self::$instances[$abstract] = $instance;
    }

    /**
     * Alias of make() — resolve a service.
     *
     * @return mixed
     */
    public static function get(string $abstract)
    {
        return self::make($abstract);
    }

    /**
     * Resolve a service from the container.
     *
     * @return mixed
     */
    public static function make(string $abstract)
    {
        // A bound instance wins outright (fast path).
        if (array_key_exists($abstract, self::$instances)) {
            return self::$instances[$abstract];
        }

        // A bound singleton factory.
        if (array_key_exists($abstract, self::$singletons)) {
            $concrete = self::$singletons[$abstract];
            $resolved = is_callable($concrete) ? $concrete(self::pdo()) : self::build($concrete);
            self::$instances[$abstract] = $resolved;
            return $resolved;
        }

        // A transient binding.
        if (array_key_exists($abstract, self::$bindings)) {
            $concrete = self::$bindings[$abstract];
            return is_callable($concrete) ? $concrete(self::pdo()) : self::build($concrete);
        }

        return self::build($abstract);
    }

    /**
     * Whether the container can resolve the given abstract.
     */
    public static function has(string $abstract): bool
    {
        return isset(self::$instances[$abstract])
            || isset(self::$singletons[$abstract])
            || isset(self::$bindings[$abstract])
            || class_exists($abstract);
    }

    /**
     * Clear all bindings and instances (used by tests between cases).
     */
    public static function flush(): void
    {
        self::$instances = [];
        self::$bindings = [];
        self::$singletons = [];
        self::$booted = false;
    }

    /**
     * Build (and auto-wire) a concrete class by reflecting its constructor.
     *
     * @param string $concrete
     * @return mixed
     */
    private static function build(string $concrete)
    {
        if (!class_exists($concrete)) {
            throw new RuntimeException("Container: cannot resolve '{$concrete}'");
        }
        $ref = new ReflectionClass($concrete);
        $ctor = $ref->getConstructor();
        if ($ctor === null) {
            return $ref->newInstance();
        }

        $args = [];
        foreach ($ctor->getParameters() as $param) {
            $args[] = self::resolveParameter($concrete, $param);
        }

        return $ref->newInstanceArgs($args);
    }

    /**
     * Resolve a single constructor parameter.
     *
     * @return mixed
     */
    private static function resolveParameter(string $for, \ReflectionParameter $param)
    {
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            $typeName = $type->getName();
            // A registered binding / singleton for this type.
            if (isset(self::$singletons[$typeName])) {
                return self::make($typeName);
            }
            if (isset(self::$bindings[$typeName])) {
                return self::make($typeName);
            }
            if (isset(self::$instances[$typeName])) {
                return self::$instances[$typeName];
            }
            // The shared PDO connection.
            if ($typeName === PDO::class) {
                return self::pdo();
            }
            // Recurse into another resolvable service.
            if (class_exists($typeName)) {
                return self::make($typeName);
            }
        }
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }
        if ($param->allowsNull()) {
            return null;
        }
        throw new RuntimeException("Container: cannot resolve parameter \${$param->getName()} for '{$for}'");
    }

    /**
     * Shared PDO connection (existing singleton), passed to factory callables
     * and used for `PDO`-typed constructor parameters.
     */
    private static function pdo(): \PDO
    {
        return Database::getInstance()->getConnection();
    }
}
