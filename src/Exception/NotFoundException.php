<?php
declare(strict_types=1);
/*
 * PHP Unison Fiber Framework
 * https://github.com/php-puff/di
 * https://github.com/php-puff/di/issues
 * Copyright (c) Puff
 */

namespace Puff\Di\Exception;

use Psr\Container\NotFoundExceptionInterface;

final class NotFoundException extends ContainerException implements NotFoundExceptionInterface
{
}
