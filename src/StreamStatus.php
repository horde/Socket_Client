<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Jan Schneider <jan@horde.org>
 * @copyright 2013-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Socket_Client
 */

namespace Horde\Socket\Client;

/**
 * Typed representation of stream metadata.
 *
 * Replaces the raw associative array returned by stream_get_meta_data().
 */
final readonly class StreamStatus
{
    public function __construct(
        public bool $timedOut,
        public bool $blocked,
        public bool $eof,
        public int $unreadBytes,
    ) {}

    /**
     * Build from the array returned by stream_get_meta_data().
     *
     * @param array<string, mixed> $meta
     */
    public static function fromMetadata(array $meta): self
    {
        return new self(
            timedOut: (bool) ($meta['timed_out'] ?? false),
            blocked: (bool) ($meta['blocked'] ?? false),
            eof: (bool) ($meta['eof'] ?? false),
            unreadBytes: (int) ($meta['unread_bytes'] ?? 0),
        );
    }
}
