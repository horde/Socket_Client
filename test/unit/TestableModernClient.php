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
use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\SecureMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Testable subclass of the modern Client that bypasses real socket connections.
 *
 * Overrides connect() to use a php://memory stream, allowing unit tests
 * to exercise all public methods without network access.
 */
class TestableModernClient extends Client
{
    protected function connect(): void
    {
        $this->stream = fopen('php://memory', 'r+');
        $this->connected = true;
        $this->secure = match ($this->config->secure) {
            SecureMode::Ssl, SecureMode::Tlsv1 => true,
            default => false,
        };
    }
}
