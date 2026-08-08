<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di;

use Psr\Container\ContainerInterface;

abstract class Facade
{
    protected static ?ContainerInterface $container = null;

    /** @var array<string, mixed> Explicit test/runtime replacements only. */
    protected static array $swaps = [];

    public static function setFacadeApplication(?ContainerInterface $container): void
    {
        static::$container = $container;
        static::$swaps = [];
    }

    public static function getFacadeApplication(): ?ContainerInterface
    {
        return static::$container;
    }

    public static function swap(mixed $instance): mixed
    {
        $accessor = static::getFacadeAccessor();
        return static::$swaps[$accessor] = $instance;
    }

    public static function clearResolvedInstance(string $name): void
    {
        unset(static::$swaps[$name]);
    }

    public static function clearResolvedInstances(): void
    {
        static::$swaps = [];
    }

    public static function getFacadeRoot(): mixed
    {
        $accessor = static::getFacadeAccessor();
        if (\array_key_exists($accessor, static::$swaps)) {
            return static::$swaps[$accessor];
        }
        if (static::$container === null) {
            throw new \RuntimeException('Facade container is not configured.');
        }
        return static::$container->get($accessor);
    }

    /** @param array<int|string, mixed> $arguments */
    public static function __callStatic(string $method, array $arguments): mixed
    {
        $instance = static::getFacadeRoot();
        if (!\is_object($instance)) {
            throw new \RuntimeException('Facade root is not an object.');
        }
        return $instance->{$method}(...$arguments);
    }

    abstract protected static function getFacadeAccessor(): string;
}
