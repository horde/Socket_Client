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
 * Immutable connection configuration DTO.
 *
 * Replaces the positional parameter list and associative arrays
 * previously passed to Horde\Socket\Client::__construct().
 */
final class ConnectionConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly SecureMode $secure = SecureMode::None,
        public readonly int $connectTimeout = 30,
        public readonly int $readTimeout = 30,
        public readonly bool $verifyPeer = true,
        public readonly bool $verifyPeerName = true,
        public readonly ?string $caFile = null,
        public readonly array $context = [],
        public readonly int $maxRetries = 3,
    ) {}
}
