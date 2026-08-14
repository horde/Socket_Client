<?php

declare(strict_types=1);

/**
 * Copyright 2013-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 */

namespace Horde\Socket\Client\Test\Unit;

use Horde\Socket\Client\ChannelBinding\ChannelBindingType;
use Horde\Socket\Client\Client;
use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\Exception\ChannelBindingException;
use Horde\Socket\Client\SecureMode;
use OpenSSLCertificate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

/**
 * Exercises TLS channel-binding support without needing a live TLS server:
 * a real self-signed certificate is generated in-process and injected into
 * the client via reflection, exactly where capturePeerCertificate() would
 * have placed the negotiated peer certificate.
 */
#[CoversClass(Client::class)]
class ChannelBindingTest extends TestCase
{
    private TestableModernClient $client;

    protected function setUp(): void
    {
        if (!extension_loaded('openssl')) {
            $this->markTestSkipped('ext-openssl is required for channel-binding tests.');
        }

        $config = new ConnectionConfig(host: 'localhost', port: 993, secure: SecureMode::Ssl);
        $this->client = new TestableModernClient($config);
    }

    protected function tearDown(): void
    {
        if ($this->client->isConnected()) {
            $this->client->close();
        }
    }

    public function testSupportsChannelBindingFalseWithoutCertificate(): void
    {
        $this->assertFalse(
            $this->client->supportsChannelBinding(ChannelBindingType::TlsServerEndPoint),
        );
    }

    public function testChannelBindingDataThrowsWithoutCertificate(): void
    {
        $this->expectException(ChannelBindingException::class);
        $this->client->channelBindingData(ChannelBindingType::TlsServerEndPoint);
    }

    public function testChannelBindingDataThrowsWhenNotSecure(): void
    {
        $config = new ConnectionConfig(host: 'localhost', port: 143);
        $client = new TestableModernClient($config);
        $this->injectCertificate($client, $this->generateCertificate('sha256'));

        $this->expectException(ChannelBindingException::class);
        $client->channelBindingData(ChannelBindingType::TlsServerEndPoint);
    }

    public function testTlsExporterThrowsUnsupported(): void
    {
        $this->injectCertificate($this->client, $this->generateCertificate('sha256'));

        $this->expectException(ChannelBindingException::class);
        $this->expectExceptionMessageMatches('/not supported/');
        $this->client->channelBindingData(ChannelBindingType::TlsExporter);
    }

    public function testTlsUniqueThrowsUnsupported(): void
    {
        $this->injectCertificate($this->client, $this->generateCertificate('sha256'));

        $this->expectException(ChannelBindingException::class);
        $this->client->channelBindingData(ChannelBindingType::TlsUnique);
    }

    public function testSupportsChannelBindingTrueWithSha256Certificate(): void
    {
        $this->injectCertificate($this->client, $this->generateCertificate('sha256'));

        $this->assertTrue(
            $this->client->supportsChannelBinding(ChannelBindingType::TlsServerEndPoint),
        );
    }

    public function testChannelBindingDataMatchesSha256Fingerprint(): void
    {
        $cert = $this->generateCertificate('sha256');
        $this->injectCertificate($this->client, $cert);

        $expected = openssl_x509_fingerprint($cert, 'sha256', true);
        $this->assertSame(
            $expected,
            $this->client->channelBindingData(ChannelBindingType::TlsServerEndPoint),
        );
    }

    public function testChannelBindingDataMatchesSha384Fingerprint(): void
    {
        $cert = $this->generateCertificate('sha384');
        $this->injectCertificate($this->client, $cert);

        $expected = openssl_x509_fingerprint($cert, 'sha384', true);
        $this->assertSame(
            $expected,
            $this->client->channelBindingData(ChannelBindingType::TlsServerEndPoint),
        );
    }

    public function testChannelBindingDataFallsBackToSha256ForSha1Signature(): void
    {
        // RFC 5929 sec. 4.1: MD5/SHA-1 signed certificates fall back to SHA-256.
        $cert = $this->generateCertificate('sha1');
        $this->injectCertificate($this->client, $cert);

        $expected = openssl_x509_fingerprint($cert, 'sha256', true);
        $this->assertSame(
            $expected,
            $this->client->channelBindingData(ChannelBindingType::TlsServerEndPoint),
        );
    }

    /**
     * @return OpenSSLCertificate|resource
     */
    private function generateCertificate(string $digestAlgo)
    {
        $config = [
            'digest_alg' => $digestAlgo,
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $privateKey = openssl_pkey_new($config);
        $csr = openssl_csr_new(['commonName' => 'horde-socket-client-test'], $privateKey, $config);

        return openssl_csr_sign($csr, null, $privateKey, 365, $config);
    }

    /**
     * @param OpenSSLCertificate|resource $certificate
     */
    private function injectCertificate(Client $client, $certificate): void
    {
        $property = new ReflectionProperty(Client::class, 'peerCertificate');
        $property->setValue($client, $certificate);
    }
}
