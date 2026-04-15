<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit\Event;

use Horde\Socket\Client\Event\ConnectionClosed;
use Horde\Socket\Client\Event\ConnectionEstablished;
use Horde\Socket\Client\Event\ConnectionFailed;
use Horde\Socket\Client\Event\SocketEvent;
use Horde\Socket\Client\Event\TlsFailed;
use Horde\Socket\Client\Event\TlsNegotiated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketEvent::class)]
#[CoversClass(ConnectionEstablished::class)]
#[CoversClass(ConnectionFailed::class)]
#[CoversClass(ConnectionClosed::class)]
#[CoversClass(TlsNegotiated::class)]
#[CoversClass(TlsFailed::class)]
class SocketEventTest extends TestCase
{
    public function testMessageAndContext(): void
    {
        $event = new ConnectionEstablished(
            'Connected',
            ['host' => 'example.com', 'port' => 993],
        );

        $this->assertSame('Connected', $event->getMessage());
        $this->assertSame(['host' => 'example.com', 'port' => 993], $event->getContext());
    }

    public function testDefaultsAreEmpty(): void
    {
        $event = new ConnectionClosed();
        $this->assertSame('', $event->getMessage());
        $this->assertSame([], $event->getContext());
    }

    public function testAllEventsExtendSocketEvent(): void
    {
        $events = [
            new ConnectionEstablished(),
            new ConnectionFailed(),
            new ConnectionClosed(),
            new TlsNegotiated(),
            new TlsFailed(),
        ];

        foreach ($events as $event) {
            $this->assertInstanceOf(SocketEvent::class, $event);
        }
    }

    public function testConnectionFailedCarriesErrorContext(): void
    {
        $event = new ConnectionFailed(
            'Connection failed',
            ['errorCode' => 111, 'errorMessage' => 'Connection refused'],
        );

        $ctx = $event->getContext();
        $this->assertSame(111, $ctx['errorCode']);
        $this->assertSame('Connection refused', $ctx['errorMessage']);
    }

    public function testTlsFailedCarriesError(): void
    {
        $event = new TlsFailed(
            'TLS negotiation failed',
            ['error' => 'certificate verify failed'],
        );

        $this->assertSame('certificate verify failed', $event->getContext()['error']);
    }
}
