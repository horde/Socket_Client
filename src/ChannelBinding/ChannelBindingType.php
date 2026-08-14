<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @copyright 2013-2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Socket_Client
 */

namespace Horde\Socket\Client\ChannelBinding;

/**
 * TLS channel-binding types (RFC 5929, RFC 9266).
 *
 * The backing value matches the binding name as it appears on the wire
 * (e.g. the SCRAM-*-PLUS GS2 header `p=<type>`), so consumers can map this
 * enum 1:1 onto a protocol library's own channel-binding type without a
 * hard dependency in either direction.
 *
 * Only `TlsServerEndPoint` is currently produceable: PHP's stream/openssl
 * API exposes the negotiated peer certificate but no API for TLS exporter
 * keying material or the Finished message, so `TlsExporter` and
 * `TlsUnique` cannot be implemented today (see php/php-src#16766).
 */
enum ChannelBindingType: string
{
    /**
     * RFC 5929. Hash of the server's TLS certificate (DER-encoded, hashed
     * with the certificate's own signature digest, or SHA-256 if that
     * digest is MD5/SHA-1 or unrecognized). Available whenever the peer
     * certificate can be captured — works on both TLS 1.2 and 1.3.
     */
    case TlsServerEndPoint = 'tls-server-end-point';

    /**
     * RFC 9266. TLS 1.3 exporter-derived binding. Not implementable: PHP
     * streams do not expose an SSL_export_keying_material() equivalent.
     */
    case TlsExporter = 'tls-exporter';

    /**
     * RFC 5929. Bound to the TLS Finished message. Not implementable: PHP
     * streams do not expose the Finished message. Also broken under TLS 1.3
     * and unsafe before RFC 7627 — would not be recommended even if it were.
     */
    case TlsUnique = 'tls-unique';
}
