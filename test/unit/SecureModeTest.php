<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\SecureMode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecureMode::class)]
class SecureModeTest extends TestCase
{
    public function testNoneHasEmptyValue(): void
    {
        $this->assertSame('', SecureMode::None->value);
    }

    public function testSslHasSslValue(): void
    {
        $this->assertSame('ssl', SecureMode::Ssl->value);
    }

    public function testTlsHasTlsValue(): void
    {
        $this->assertSame('tls', SecureMode::Tls->value);
    }

    public function testTlsv1HasTlsv1Value(): void
    {
        $this->assertSame('tlsv1', SecureMode::Tlsv1->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(SecureMode::Ssl, SecureMode::from('ssl'));
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(SecureMode::tryFrom('sslv3'));
    }

    public function testCaseCount(): void
    {
        $this->assertCount(4, SecureMode::cases());
    }
}
