<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\Exception;
use Horde_Exception_Wrapped;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;
use RuntimeException;

#[CoversClass(Exception::class)]
class ExceptionTest extends TestCase
{
    public function testExceptionExtendsHordeExceptionWrapped(): void
    {
        $exception = new Exception('test');
        $this->assertInstanceOf(Horde_Exception_Wrapped::class, $exception);
    }

    public function testExceptionIsThrowable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('socket error');
        throw new Exception('socket error');
    }

    public function testExceptionPreservesCode(): void
    {
        $exception = new Exception('test', 42);
        $this->assertSame(42, $exception->getCode());
    }

    public function testExceptionAcceptsWrappedObject(): void
    {
        $original = new RuntimeException('root cause', 99);
        $exception = new Exception($original);
        $this->assertSame('root cause', $exception->getMessage());
        $this->assertSame(99, $exception->getCode());
        $this->assertSame($original, $exception->getPrevious());
    }
}
