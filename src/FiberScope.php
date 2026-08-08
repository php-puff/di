<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di;

use Fiber;
use WeakMap;

final class FiberScope
{
    private WeakMap $fibers;
    private array $main = [];

    public function __construct()
    {
        $this->fibers = new WeakMap();
    }

    public function has(string $id): bool
    {
        return \array_key_exists($id, $this->values());
    }

    public function get(string $id): mixed
    {
        return $this->values()[$id] ?? null;
    }

    public function set(string $id, mixed $value): void
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            $this->main[$id] = $value;
            return;
        }
        $values = $this->fibers[$fiber] ?? [];
        $values[$id] = $value;
        $this->fibers[$fiber] = $values;
    }

    public function clear(): void
    {
        $fiber = Fiber::getCurrent();
        if ($fiber === null) {
            $this->main = [];
            return;
        }
        unset($this->fibers[$fiber]);
    }

    private function values(): array
    {
        $fiber = Fiber::getCurrent();
        return $fiber === null ? $this->main : ($this->fibers[$fiber] ?? []);
    }
}
