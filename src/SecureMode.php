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
 * Transport security mode for socket connections.
 *
 * sslv2/sslv3 deliberately omitted — insecure protocols removed in modern PHP.
 */
enum SecureMode: string
{
    case None = '';
    case Ssl = 'ssl';
    case Tls = 'tls';
    case Tlsv1 = 'tlsv1';
}
