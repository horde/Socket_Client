<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client;
use Horde\Socket\Client\Exception;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Client::class)]
class ClientTest extends TestCase
{
    private TestableClient $client;

    protected function setUp(): void
    {
        $this->client = new TestableClient();
    }

    protected function tearDown(): void
    {
        if ($this->client->connected) {
            $this->client->close();
        }
    }

    // -- Magic property access (__get) --

    public function testConnectedPropertyReturnsTrueAfterConstruction(): void
    {
        $this->assertTrue($this->client->connected);
    }

    public function testSecurePropertyReturnsFalseByDefault(): void
    {
        $this->assertFalse($this->client->secure);
    }

    public function testSecurePropertyReturnsTrueWhenConstructedSecure(): void
    {
        $client = new TestableClient(secure: true);
        $this->assertTrue($client->secure);
        $client->close();
    }

    public function testUndefinedPropertyReturnsNull(): void
    {
        $this->assertNull($this->client->nonexistent);
    }

    // -- Clone and serialization prevention --

    public function testCloneThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        clone $this->client;
    }

    public function testSleepThrowsLogicException(): void
    {
        $this->expectException(LogicException::class);
        $this->client->__sleep();
    }

    // -- close() --

    public function testCloseResetsState(): void
    {
        $this->assertTrue($this->client->connected);
        $this->client->close();
        $this->assertFalse($this->client->connected);
        $this->assertFalse($this->client->secure);
    }

    public function testCloseOnSecureConnectionResetsBothFlags(): void
    {
        $client = new TestableClient(secure: true);
        $this->assertTrue($client->connected);
        $this->assertTrue($client->secure);
        $client->close();
        $this->assertFalse($client->connected);
        $this->assertFalse($client->secure);
    }

    public function testCloseOnAlreadyClosedIsNoop(): void
    {
        $this->client->close();
        $this->assertFalse($this->client->connected);
        // Second close should not throw
        $this->client->close();
        $this->assertFalse($this->client->connected);
    }

    // -- getStatus() --

    public function testGetStatusReturnsArray(): void
    {
        $status = $this->client->getStatus();
        $this->assertIsArray($status);
        $this->assertArrayHasKey('timed_out', $status);
        $this->assertArrayHasKey('blocked', $status);
        $this->assertArrayHasKey('eof', $status);
        $this->assertArrayHasKey('unread_bytes', $status);
    }

    public function testGetStatusThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not connected');
        $this->client->getStatus();
    }

    // -- gets() --

    public function testGetsReadsLine(): void
    {
        $this->client->write("Hello World\n");
        // Rewind the memory stream so gets() can read it back
        $this->rewindStream();

        $line = $this->client->gets(1024);
        $this->assertSame("Hello World\n", $line);
    }

    public function testGetsRespectsSize(): void
    {
        $this->client->write("Hello World\n");
        $this->rewindStream();

        // Size of 6 means read up to 5 bytes (fgets reads size-1)
        $data = $this->client->gets(6);
        $this->assertSame('Hello', $data);
    }

    public function testGetsThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not connected');
        $this->client->gets(1024);
    }

    // -- read() --

    public function testReadReturnsRequestedBytes(): void
    {
        $this->client->write('ABCDEFGHIJ');
        $this->rewindStream();

        $data = $this->client->read(5);
        $this->assertSame('ABCDE', $data);
    }

    public function testReadThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not connected');
        $this->client->read(10);
    }

    // -- write() --

    public function testWritePutsDataOnStream(): void
    {
        $this->client->write('test data');
        $this->rewindStream();

        $data = $this->client->read(9);
        $this->assertSame('test data', $data);
    }

    public function testWriteThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Not connected');
        $this->client->write('data');
    }

    /**
     * Rewind the internal memory stream so read operations can see written data.
     *
     * Uses reflection to access the protected _stream property.
     */
    private function rewindStream(): void
    {
        $ref = new ReflectionProperty(Client::class, '_stream');
        $stream = $ref->getValue($this->client);
        rewind($stream);
    }
}
