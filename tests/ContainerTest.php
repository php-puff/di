<?php

/*
 * PHP Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

declare(strict_types=1);

namespace Puff\Di\Tests;

use PHPUnit\Framework\TestCase;
use Puff\Di\Container;

final class ContainerTest extends TestCase
{
    public function testAutowiresAndCachesSingletons(): void
    {
        $container = new Container();
        $container->singleton(Dependency::class);

        $service = $container->make(Service::class);

        self::assertInstanceOf(Dependency::class, $service->dependency);
        self::assertSame($container->make(Dependency::class), $service->dependency);
    }

    public function testAliasesAndCallableInjection(): void
    {
        $container = new Container();
        $container->singleton(Dependency::class);
        $container->alias(Dependency::class, 'dependency');

        $result = $container->call(
            static fn (Dependency $dependency, string $name): array => [$dependency, $name],
            ['name' => 'Puff'],
        );

        self::assertSame($container->make('dependency'), $result[0]);
        self::assertSame('Puff', $result[1]);
    }

    public function testFiberScopedInstancesAreIsolated(): void
    {
        $container = new Container();
        $main = new \stdClass();
        $container->scopedInstance('request', $main);

        $fiberValue = null;
        $fiber = new \Fiber(function () use ($container, &$fiberValue): void {
            $request = new \stdClass();
            $container->scopedInstance('request', $request);
            $fiberValue = $container->make('request');
            $container->clearScope();
        });
        $fiber->start();

        self::assertNotSame($main, $fiberValue);
        self::assertSame($main, $container->make('request'));
    }

    public function testScopedBindingsAreReusedOnlyInsideCurrentFiber(): void
    {
        $container = new Container();
        $container->scoped('request', static fn (): object => new \stdClass());

        $main = $container->make('request');
        self::assertSame($main, $container->make('request'));

        $fiberValue = null;
        $fiber = new \Fiber(function () use ($container, &$fiberValue): void {
            $fiberValue = $container->make('request');
            self::assertSame($fiberValue, $container->make('request'));
            $container->clearScope();
            self::assertNotSame($fiberValue, $container->make('request'));
        });
        $fiber->start();

        self::assertNotSame($main, $fiberValue);
        self::assertSame($main, $container->make('request'));
    }

    public function testScopeClearCallbacksAreInvoked(): void
    {
        $container = new Container();
        $calls = 0;
        $container->onScopeClear(static function () use (&$calls): void {
            ++$calls;
        });

        $container->clearScope();

        self::assertSame(1, $calls);
    }
}

final class Dependency
{
}

final class Service
{
    public function __construct(public readonly Dependency $dependency)
    {
    }
}
