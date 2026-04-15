<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit\Exception;

use Horde\Socket\Client\Exception\ConnectionException;
use Horde\Socket\Client\Exception\SocketException;
use Horde\Socket\Client\Exception\StreamException;
use Horde\Socket\Client\Exception\TimeoutException;
use Horde_Exception_Wrapped;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketException::class)]
#[CoversClass(ConnectionException::class)]
#[CoversClass(StreamException::class)]
#[CoversClass(TimeoutException::class)]
class ExceptionHierarchyTest extends TestCase
{
    public function testSocketExceptionExtendsWrapped(): void
    {
        $e = new SocketException('test');
        $this->assertInstanceOf(Horde_Exception_Wrapped::class, $e);
    }

    public function testConnectionExceptionExtendsSocket(): void
    {
        $e = new ConnectionException('connect failed');
        $this->assertInstanceOf(SocketException::class, $e);
    }

    public function testTimeoutExceptionExtendsSocket(): void
    {
        $e = new TimeoutException('timed out');
        $this->assertInstanceOf(SocketException::class, $e);
    }

    public function testStreamExceptionExtendsSocket(): void
    {
        $e = new StreamException('broken pipe');
        $this->assertInstanceOf(SocketException::class, $e);
    }

    public function testAllAreCatchableAsSocketException(): void
    {
        $exceptions = [
            new ConnectionException('a'),
            new TimeoutException('b'),
            new StreamException('c'),
        ];

        foreach ($exceptions as $e) {
            $this->assertInstanceOf(SocketException::class, $e);
        }
    }
}
