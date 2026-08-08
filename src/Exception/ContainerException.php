<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di\Exception;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \RuntimeException implements ContainerExceptionInterface
{
}
