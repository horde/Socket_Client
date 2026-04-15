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

/**
 * Testable subclass of Client that bypasses real socket connections.
 *
 * Overrides _connect() to use a php://memory stream, allowing unit tests
 * to exercise all public methods without network access.
 */
class TestableClient extends Client
{
    protected bool $connectSecure = false;

    /**
     * @param bool $secure Whether to simulate a secure connection.
     */
    public function __construct(bool $secure = false)
    {
        $this->connectSecure = $secure;
        parent::__construct('localhost', 0, 30, false);
    }

    protected function _connect(
        $host,
        $port,
        $timeout,
        $secure,
        $context,
        $retries = 0,
    ): void {
        $this->_stream = fopen('php://memory', 'r+');
        $this->_connected = true;
        $this->_secure = $this->connectSecure;
    }
}
