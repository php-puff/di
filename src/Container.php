<?php

declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di;

use ArrayAccess;
use Closure;
use Psr\Container\ContainerInterface;
use Puff\Di\Exception\ContainerException;
use Puff\Di\Exception\NotFoundException;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

class Container implements ContainerInterface, ArrayAccess
{
    protected static ?self $instance = null;

    /** @var array<string, array{concrete: mixed, shared: bool}> */
    protected array $bindings = [];
    protected array $instances = [];
    protected array $aliases = [];
    protected array $resolved = [];
    protected array $beforeResolvingCallbacks = [];
    protected array $resolvingCallbacks = [];
    protected array $afterResolvingCallbacks = [];
    /** @var list<callable(): void> */
    private array $scopeClearCallbacks = [];
    private FiberScope $scope;

    public function __construct()
    {
        $this->scope = new FiberScope();
        $this->instance('app', $this);
        $this->instance(ContainerInterface::class, $this);
        $this->instance(self::class, $this);
    }

    public static function setInstance(?self $container = null): ?self
    {
        Facade::setFacadeApplication($container);
        return self::$instance = $container;
    }

    public static function getInstance(): ?self
    {
        return self::$instance;
    }

    public function bind(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        $this->bindings[$this->getAlias($abstract)] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => $shared,
        ];
    }

    public function bindIf(string $abstract, mixed $concrete = null, bool $shared = false): void
    {
        if (!$this->bound($abstract)) {
            $this->bind($abstract, $concrete, $shared);
        }
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bind($abstract, $concrete, true);
    }

    public function singletonIf(string $abstract, mixed $concrete = null): void
    {
        if (!$this->bound($abstract)) {
            $this->singleton($abstract, $concrete);
        }
    }

    public function instance(string $abstract, mixed $instance): mixed
    {
        $abstract = $this->getAlias($abstract);
        $this->instances[$abstract] = $instance;
        $this->resolved[$abstract] = true;
        return $instance;
    }

    public function scopedInstance(string $abstract, object $instance): object
    {
        $this->scope->set($this->getAlias($abstract), $instance);
        return $instance;
    }

    public function clearScope(): void
    {
        foreach ($this->scopeClearCallbacks as $callback) {
            $callback();
        }
        $this->scope->clear();
    }

    public function onScopeClear(callable $callback): void
    {
        $this->scopeClearCallbacks[] = $callback;
    }

    public function get(string $id): mixed
    {
        if (!$this->bound($id) && !\class_exists($id)) {
            throw new NotFoundException("Service [{$id}] is not defined.");
        }
        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return $this->bound($id) || \class_exists($id);
    }

    public function make(string $abstract, array $parameters = [], bool $events = true): mixed
    {
        return $this->resolve($abstract, $parameters, $events);
    }

    public function resolve(string $abstract, array $parameters = [], bool $events = true): mixed
    {
        $abstract = $this->getAlias($abstract);
        if ($this->scope->has($abstract)) {
            return $this->scope->get($abstract);
        }
        if ($parameters === [] && \array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        if ($events) {
            $this->fire($this->beforeResolvingCallbacks, $abstract, null);
        }
        $binding = $this->bindings[$abstract] ?? ['concrete' => $abstract, 'shared' => false];
        $object = $this->build($binding['concrete'], $parameters);
        if ($binding['shared'] && $parameters === []) {
            $this->instances[$abstract] = $object;
        }
        $this->resolved[$abstract] = true;
        if ($events) {
            $this->fire($this->resolvingCallbacks, $abstract, $object);
            $this->fire($this->afterResolvingCallbacks, $abstract, $object);
        }
        return $object;
    }

    public function build(mixed $concrete, array $parameters = []): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete($this, ...\array_values($parameters));
        }
        if (!\is_string($concrete) || !\class_exists($concrete)) {
            return $concrete;
        }

        $reflector = new ReflectionClass($concrete);
        if (!$reflector->isInstantiable()) {
            throw new ContainerException("Class [{$concrete}] is not instantiable.");
        }
        $constructor = $reflector->getConstructor();
        if ($constructor === null) {
            return new $concrete();
        }
        return $reflector->newInstanceArgs($this->dependencies($constructor, $parameters));
    }

    public function call(callable|string|array $callback, array $parameters = []): mixed
    {
        if (\is_string($callback) && \str_contains($callback, '@')) {
            [$class, $method] = \explode('@', $callback, 2);
            $callback = [$this->make($class), $method];
        } elseif (\is_string($callback) && \str_contains($callback, '::')) {
            $callback = \explode('::', $callback, 2);
        }

        if (\is_array($callback) && \is_string($callback[0]) && !\is_callable($callback)) {
            $callback[0] = $this->make($callback[0]);
        }

        $reflection = \is_array($callback)
            ? new ReflectionMethod($callback[0], $callback[1])
            : new ReflectionFunction(Closure::fromCallable($callback));
        return $callback(...$this->dependencies($reflection, $parameters));
    }

    public function alias(string $abstract, string $alias): void
    {
        $this->aliases[$alias] = $abstract;
    }

    public function getAlias(string $abstract): string
    {
        $seen = [];
        while (isset($this->aliases[$abstract])) {
            if (isset($seen[$abstract])) {
                throw new ContainerException("Alias cycle detected for [{$abstract}].");
            }
            $seen[$abstract] = true;
            $abstract = $this->aliases[$abstract];
        }
        return $abstract;
    }

    public function bound(string $abstract): bool
    {
        $abstract = $this->getAlias($abstract);
        return isset($this->bindings[$abstract]) || \array_key_exists($abstract, $this->instances);
    }

    public function resolved(string $abstract): bool
    {
        return isset($this->resolved[$this->getAlias($abstract)]);
    }

    public function lastBinding(): ?string
    {
        return \array_key_last($this->bindings);
    }

    public function beforeResolving(string|callable $abstract, ?callable $callback = null): void
    {
        $this->addCallback($this->beforeResolvingCallbacks, $abstract, $callback);
    }

    public function resolving(string|callable $abstract, ?callable $callback = null): void
    {
        $this->addCallback($this->resolvingCallbacks, $abstract, $callback);
    }

    public function afterResolving(string|callable $abstract, ?callable $callback = null): void
    {
        $this->addCallback($this->afterResolvingCallbacks, $abstract, $callback);
    }

    public function offsetExists(mixed $offset): bool
    {
        return $this->bound((string) $offset);
    }
    public function offsetGet(mixed $offset): mixed
    {
        return $this->make((string) $offset);
    }
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $offset === null ? throw new ContainerException('Service id cannot be null.') : $this->instance((string) $offset, $value);
    }
    public function offsetUnset(mixed $offset): void
    {
        $id = $this->getAlias((string) $offset);
        unset($this->bindings[$id], $this->instances[$id], $this->resolved[$id]);
    }
    public function __get(string $id): mixed
    {
        return $this->make($id);
    }
    public function __isset(string $id): bool
    {
        return $this->bound($id);
    }

    private function dependencies(ReflectionFunctionAbstract $function, array $provided): array
    {
        $values = [];
        $numeric = \array_values(\array_filter($provided, 'is_int', ARRAY_FILTER_USE_KEY));
        foreach ($function->getParameters() as $parameter) {
            if (\array_key_exists($parameter->getName(), $provided)) {
                $values[] = $provided[$parameter->getName()];
                continue;
            }
            if ($numeric !== []) {
                $values[] = \array_shift($numeric);
                continue;
            }
            $values[] = $this->parameter($parameter);
        }
        return $values;
    }

    private function parameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->make($type->getName());
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        if ($parameter->allowsNull()) {
            return null;
        }
        throw new ContainerException("Unable to resolve parameter [\${$parameter->getName()}].");
    }

    private function addCallback(array &$callbacks, string|callable $abstract, ?callable $callback): void
    {
        if (\is_callable($abstract) && $callback === null) {
            $callbacks['*'][] = $abstract;
            return;
        }
        $callbacks[$this->getAlias((string) $abstract)][] = $callback;
    }

    private function fire(array $callbacks, string $abstract, mixed $object): void
    {
        foreach ([...($callbacks['*'] ?? []), ...($callbacks[$abstract] ?? [])] as $callback) {
            $callback($object, $this);
        }
    }
}
