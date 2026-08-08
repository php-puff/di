<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di;

abstract class ServiceProvider
{
    protected bool $defer = false;
    protected array $bootingCallbacks = [];
    protected array $bootedCallbacks = [];

    public function __construct(protected Container $app)
    {
    }

    public function register()
    {
    }

    public function booting(callable $callback): void
    {
        $this->bootingCallbacks[] = $callback;
    }

    public function booted(callable $callback): void
    {
        $this->bootedCallbacks[] = $callback;
    }

    public function callBootingCallbacks(): void
    {
        foreach ($this->bootingCallbacks as $callback) {
            $this->app->call($callback);
        }
    }

    public function callBootedCallbacks(): void
    {
        foreach ($this->bootedCallbacks as $callback) {
            $this->app->call($callback);
        }
    }

    public function isDeferred(): bool
    {
        return $this->defer;
    }

    public function getBootingCallbacks(): array
    {
        return $this->bootingCallbacks;
    }

    public function getBootedCallbacks(): array
    {
        return $this->bootedCallbacks;
    }

    protected function mergeConfigFrom(string $path, string $key): void
    {
        if (!\is_file($path) || !$this->app->bound('config')) {
            return;
        }
        $config = $this->app->make('config');
        $current = (array) $config->get($key, []);
        $config->set($key, \array_replace_recursive((array) require $path, $current));
    }
}
