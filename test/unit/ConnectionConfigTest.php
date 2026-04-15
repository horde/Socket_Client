<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ConnectionConfig::class)]
class ConnectionConfigTest extends TestCase
{
    public function testRequiredParameters(): void
    {
        $config = new ConnectionConfig(host: 'mail.example.com', port: 993);
        $this->assertSame('mail.example.com', $config->host);
        $this->assertSame(993, $config->port);
    }

    public function testDefaults(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 143);
        $this->assertSame(SecureMode::None, $config->secure);
        $this->assertSame(30, $config->connectTimeout);
        $this->assertSame(30, $config->readTimeout);
        $this->assertTrue($config->verifyPeer);
        $this->assertTrue($config->verifyPeerName);
        $this->assertNull($config->caFile);
        $this->assertSame([], $config->context);
        $this->assertSame(3, $config->maxRetries);
    }

    public function testCustomValues(): void
    {
        $config = new ConnectionConfig(
            host: 'imap.example.org',
            port: 993,
            secure: SecureMode::Ssl,
            connectTimeout: 10,
            readTimeout: 60,
            verifyPeer: false,
            verifyPeerName: false,
            caFile: '/etc/ssl/certs/ca-bundle.crt',
            context: ['ssl' => ['ciphers' => 'HIGH']],
            maxRetries: 5,
        );

        $this->assertSame('imap.example.org', $config->host);
        $this->assertSame(993, $config->port);
        $this->assertSame(SecureMode::Ssl, $config->secure);
        $this->assertSame(10, $config->connectTimeout);
        $this->assertSame(60, $config->readTimeout);
        $this->assertFalse($config->verifyPeer);
        $this->assertFalse($config->verifyPeerName);
        $this->assertSame('/etc/ssl/certs/ca-bundle.crt', $config->caFile);
        $this->assertSame(['ssl' => ['ciphers' => 'HIGH']], $config->context);
        $this->assertSame(5, $config->maxRetries);
    }

    public function testImmutability(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 143);
        $ref = new \ReflectionProperty(ConnectionConfig::class, 'host');
        $this->assertTrue($ref->isReadOnly());
    }
}
