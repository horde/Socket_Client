<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\Client;
use Horde\Socket\Client\ClientInterface;
use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\SecureMode;
use Horde\Socket\Client\StreamStatus;
use Horde\Socket\Client\Event\ConnectionClosed;
use Horde\Socket\Client\Event\SocketEvent;
use Horde\Socket\Client\Exception\StreamException;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use ReflectionProperty;

#[CoversClass(Client::class)]
class ModernClientTest extends TestCase
{
    private TestableModernClient $client;
    private ConnectionConfig $config;

    protected function setUp(): void
    {
        $this->config = new ConnectionConfig(host: 'localhost', port: 143);
        $this->client = new TestableModernClient($this->config);
    }

    protected function tearDown(): void
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }

    // -- Interface conformance --

    public function testImplementsClientInterface(): void
    {
        $this->assertInstanceOf(ClientInterface::class, $this->client);
    }

    // -- isConnected / isSecure --

    public function testIsConnectedAfterConstruction(): void
    {
        $this->assertTrue($this->client->isConnected());
    }

    public function testIsSecureFalseByDefault(): void
    {
        $this->assertFalse($this->client->isSecure());
    }

    public function testIsSecureTrueForSslMode(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 993, secure: SecureMode::Ssl);
        $client = new TestableModernClient($config);
        $this->assertTrue($client->isSecure());
        $client->close();
    }

    public function testIsSecureTrueForTlsv1Mode(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 993, secure: SecureMode::Tlsv1);
        $client = new TestableModernClient($config);
        $this->assertTrue($client->isSecure());
        $client->close();
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
        $this->client->close();
        $this->assertFalse($this->client->isConnected());
        $this->assertFalse($this->client->isSecure());
    }

    public function testCloseOnAlreadyClosedIsNoop(): void
    {
        $this->client->close();
        $this->client->close();
        $this->assertFalse($this->client->isConnected());
    }

    // -- getStatus() --

    public function testGetStatusReturnsStreamStatus(): void
    {
        $status = $this->client->getStatus();
        $this->assertInstanceOf(StreamStatus::class, $status);
        $this->assertFalse($status->timedOut);
        $this->assertFalse($status->eof);
    }

    public function testGetStatusThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(StreamException::class);
        $this->expectExceptionMessage('Not connected');
        $this->client->getStatus();
    }

    // -- gets() --

    public function testGetsReadsLine(): void
    {
        $this->client->write("Hello World\n");
        $this->rewindStream();

        $line = $this->client->gets(1024);
        $this->assertSame("Hello World\n", $line);
    }

    public function testGetsRespectsSize(): void
    {
        $this->client->write("Hello World\n");
        $this->rewindStream();

        $data = $this->client->gets(6);
        $this->assertSame('Hello', $data);
    }

    public function testGetsThrowsOnClosedStream(): void
    {
        $this->client->close();
        $this->expectException(StreamException::class);
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
        $this->expectException(StreamException::class);
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
        $this->expectException(StreamException::class);
        $this->client->write('data');
    }

    // -- PSR-14 event dispatch --

    public function testCloseEmitsConnectionClosedEvent(): void
    {
        $dispatched = [];
        $dispatcher = $this->createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatched): object {
                $dispatched[] = $event;
                return $event;
            });

        $client = new TestableModernClient($this->config, $dispatcher);
        $client->close();

        $this->assertCount(1, $dispatched);
        $this->assertInstanceOf(ConnectionClosed::class, $dispatched[0]);
        $this->assertSame('localhost', $dispatched[0]->getContext()['host']);
        $this->assertSame(143, $dispatched[0]->getContext()['port']);
    }

    public function testNullDispatcherDoesNotError(): void
    {
        $client = new TestableModernClient($this->config);
        // close() calls emit() internally — should not throw without dispatcher
        $client->close();
        $this->assertFalse($client->isConnected());
    }

    /**
     * Rewind the internal memory stream so read operations can see written data.
     */
    private function rewindStream(): void
    {
        $ref = new ReflectionProperty(Client::class, 'stream');
        $stream = $ref->getValue($this->client);
        rewind($stream);
    }
}
