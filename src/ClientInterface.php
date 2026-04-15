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
 * Interface for a network socket client.
 */
interface ClientInterface
{
    public function isConnected(): bool;

    public function isSecure(): bool;

    /**
     * Upgrade an existing plaintext connection to TLS.
     *
     * @return bool Whether TLS was successfully negotiated.
     */
    public function startTls(): bool;

    public function close(): void;

    public function getStatus(): StreamStatus;

    /**
     * Read a line of data (up to $size - 1 bytes, or until newline/EOF).
     */
    public function gets(int $size): string;

    /**
     * Read exactly $size bytes from the socket.
     */
    public function read(int $size): string;

    /**
     * Write data to the socket.
     */
    public function write(string $data): void;
}
