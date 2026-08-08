<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di\Tests;

use Puff\Di\Container;
use Puff\Di\Facade;
use PHPUnit\Framework\TestCase;

final class FacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance();
    }

    public function testContainerInstanceAutomaticallyConfiguresFacade(): void
    {
        $container = new Container();
        $calls = 0;
        $container->bind('service', static function () use (&$calls): object {
            ++$calls;
            return new class () {
                public function value(): int
                {
                    return 7;
                }
            };
        });

        Container::setInstance($container);

        self::assertSame(7, TestFacade::value());
        self::assertSame(7, TestFacade::value());
        self::assertSame(2, $calls);
    }
}

/** @method static int value() */
final class TestFacade extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'service';
    }
}
