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

use Horde\Socket\Client\ChannelBinding\ChannelBindingType;
use Horde\Socket\Client\Exception\ChannelBindingException;

/**
 * Interface for a network socket client.
 */
interface ClientInterface
{
    public function isConnected(): bool;

    public function isSecure(): bool;

    /**
     * Whether the live connection can currently produce the given
     * TLS channel-binding type (RFC 5929 / RFC 9266).
     */
    public function supportsChannelBinding(ChannelBindingType $type): bool;

    /**
     * The TLS channel-binding data for the given type.
     *
     * Intended to be handed to a SASL library's channel-binding provider
     * seam (e.g. `Horde\Sasl\ChannelBinding\ChannelBindingProvider`) for the
     * SCRAM-*-PLUS family of mechanisms.
     *
     * @throws ChannelBindingException If the connection isn't secure, no
     *                                  peer certificate was captured, or the
     *                                  type cannot be produced by PHP's
     *                                  stream/openssl API.
     */
    public function channelBindingData(ChannelBindingType $type): string;

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
