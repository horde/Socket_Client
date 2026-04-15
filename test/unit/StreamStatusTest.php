<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\StreamStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StreamStatus::class)]
class StreamStatusTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $status = new StreamStatus(
            timedOut: true,
            blocked: false,
            eof: true,
            unreadBytes: 42,
        );

        $this->assertTrue($status->timedOut);
        $this->assertFalse($status->blocked);
        $this->assertTrue($status->eof);
        $this->assertSame(42, $status->unreadBytes);
    }

    public function testFromMetadata(): void
    {
        $meta = [
            'timed_out' => false,
            'blocked' => true,
            'eof' => false,
            'unread_bytes' => 1024,
            'stream_type' => 'tcp_socket/ssl',
            'wrapper_type' => '',
        ];

        $status = StreamStatus::fromMetadata($meta);
        $this->assertFalse($status->timedOut);
        $this->assertTrue($status->blocked);
        $this->assertFalse($status->eof);
        $this->assertSame(1024, $status->unreadBytes);
    }

    public function testFromMetadataWithMissingKeys(): void
    {
        $status = StreamStatus::fromMetadata([]);
        $this->assertFalse($status->timedOut);
        $this->assertFalse($status->blocked);
        $this->assertFalse($status->eof);
        $this->assertSame(0, $status->unreadBytes);
    }
}
