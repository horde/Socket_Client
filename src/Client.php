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

use InvalidArgumentException;
use LogicException;
use OpenSSLCertificate;
use Psr\EventDispatcher\EventDispatcherInterface;
use Horde\Socket\Client\ChannelBinding\ChannelBindingType;
use Horde\Socket\Client\Event\ConnectionClosed;
use Horde\Socket\Client\Event\ConnectionEstablished;
use Horde\Socket\Client\Event\ConnectionFailed;
use Horde\Socket\Client\Event\TlsFailed;
use Horde\Socket\Client\Event\TlsNegotiated;
use Horde\Socket\Client\Exception\ChannelBindingException;
use Horde\Socket\Client\Exception\ConnectionException;
use Horde\Socket\Client\Exception\StreamException;
use Horde\Socket\Client\Exception\TimeoutException;

/**
 * Network socket client with typed configuration and optional PSR-14 events.
 *
 * This is the modern replacement for Horde\Socket\Client (lib/).
 * The two implementations coexist independently — consumers migrate
 * at their own pace.
 */
class Client implements ClientInterface
{
    protected bool $connected = false;

    protected bool $secure = false;

    /** @var resource|null */
    protected $stream = null;

    /** @var OpenSSLCertificate|resource|null Captured negotiated peer certificate. */
    protected mixed $peerCertificate = null;

    private ?EventDispatcherInterface $dispatcher;

    protected ConnectionConfig $config;

    /**
     * @throws ConnectionException  When the connection cannot be established.
     * @throws InvalidArgumentException  When a secure mode requires the openssl extension.
     */
    public function __construct(
        ConnectionConfig $config,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        $this->config = $config;
        $this->dispatcher = $dispatcher;

        if ($config->secure !== SecureMode::None && !extension_loaded('openssl')) {
            throw new InvalidArgumentException(
                'Secure connections require the PHP openssl extension.',
            );
        }

        $this->connect();
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    public function isSecure(): bool
    {
        return $this->secure;
    }

    public function startTls(): bool
    {
        if (!$this->connected || $this->secure) {
            return false;
        }

        $mode = STREAM_CRYPTO_METHOD_TLS_CLIENT;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) {
            $mode |= STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT
                | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $result = stream_socket_enable_crypto($this->stream, true, $mode);
        } finally {
            restore_error_handler();
        }

        if ($result === true) {
            $this->secure = true;
            $this->capturePeerCertificate();
            $this->emit(new TlsNegotiated(
                'TLS negotiated',
                ['host' => $this->config->host, 'port' => $this->config->port],
            ));
            return true;
        }

        $this->emit(new TlsFailed(
            $error ?? 'TLS negotiation failed',
            ['host' => $this->config->host, 'port' => $this->config->port, 'error' => $error],
        ));

        return false;
    }

    public function close(): void
    {
        if (!$this->connected) {
            return;
        }

        // Best-effort close — suppression is acceptable here.
        if (is_resource($this->stream)) {
            @fclose($this->stream);
        }

        $this->connected = false;
        $this->secure = false;
        $this->stream = null;
        $this->peerCertificate = null;

        $this->emit(new ConnectionClosed(
            'Connection closed',
            ['host' => $this->config->host, 'port' => $this->config->port],
        ));
    }

    public function getStatus(): StreamStatus
    {
        $this->requireStream();
        return StreamStatus::fromMetadata(stream_get_meta_data($this->stream));
    }

    public function supportsChannelBinding(ChannelBindingType $type): bool
    {
        return $type === ChannelBindingType::TlsServerEndPoint
            && $this->secure
            && $this->peerCertificate !== null;
    }

    public function channelBindingData(ChannelBindingType $type): string
    {
        if ($type !== ChannelBindingType::TlsServerEndPoint) {
            throw new ChannelBindingException(sprintf(
                '%s channel binding is not supported: PHP\'s stream/openssl API'
                    . ' exposes no equivalent of SSL_export_keying_material() or'
                    . ' the TLS Finished message (see php/php-src#16766).',
                $type->value,
            ));
        }

        if (!$this->secure || $this->peerCertificate === null) {
            throw new ChannelBindingException(
                'No TLS peer certificate available for channel binding.'
                    . ' The connection must be secure and the server must have'
                    . ' presented a certificate.',
            );
        }

        $parsed = openssl_x509_parse($this->peerCertificate);
        if ($parsed === false) {
            throw new ChannelBindingException('Unable to parse the peer certificate.');
        }

        $algorithm = $this->fingerprintAlgorithm($parsed);
        $hash = openssl_x509_fingerprint($this->peerCertificate, $algorithm, true);
        if ($hash === false) {
            throw new ChannelBindingException('Unable to compute the certificate fingerprint.');
        }

        return $hash;
    }

    public function gets(int $size): string
    {
        $this->requireStream();

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $data = fgets($this->stream, $size);
        } finally {
            restore_error_handler();
        }

        if ($data === false) {
            throw new StreamException($error ?? 'Error reading line from socket');
        }

        return $data;
    }

    public function read(int $size): string
    {
        $this->requireStream();

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $data = fread($this->stream, $size);
        } finally {
            restore_error_handler();
        }

        if ($data === false) {
            throw new StreamException($error ?? 'Error reading data from socket');
        }

        return $data;
    }

    public function write(string $data): void
    {
        $this->requireStream();

        $error = null;
        set_error_handler(static function (int $severity, string $message) use (&$error): bool {
            $error = $message;
            return true;
        });
        try {
            $result = fwrite($this->stream, $data);
        } finally {
            restore_error_handler();
        }

        if ($result === false) {
            $status = StreamStatus::fromMetadata(stream_get_meta_data($this->stream));
            if ($status->timedOut) {
                throw new TimeoutException($error ?? 'Timed out writing data to socket');
            }
            throw new StreamException($error ?? 'Error writing data to socket');
        }
    }

    /**
     * This object cannot be cloned.
     */
    public function __clone()
    {
        throw new LogicException('Object cannot be cloned.');
    }

    /**
     * This object cannot be serialized.
     */
    public function __sleep()
    {
        throw new LogicException('Object cannot be serialized.');
    }

    /**
     * Establish the socket connection with retry logic.
     */
    protected function connect(): void
    {
        $conn = match ($this->config->secure) {
            SecureMode::Ssl => 'ssl://',
            SecureMode::Tlsv1 => 'tls://',
            SecureMode::Tls, SecureMode::None => 'tcp://',
        };
        $conn .= $this->config->host . ':' . $this->config->port;

        $context = array_replace_recursive(
            [
                'ssl' => [
                    'verify_peer' => $this->config->verifyPeer,
                    'verify_peer_name' => $this->config->verifyPeerName,
                    'capture_peer_cert' => true,
                ],
            ],
            $this->config->context,
        );

        if ($this->config->caFile !== null) {
            $context['ssl']['cafile'] = $this->config->caFile;
        }

        $streamContext = stream_context_create($context);
        $lastErrorNumber = 0;
        $lastErrorString = '';

        for ($attempt = 0; $attempt <= $this->config->maxRetries; $attempt++) {
            $errorNumber = 0;
            $errorString = '';

            $error = null;
            set_error_handler(static function (int $severity, string $message) use (&$error): bool {
                $error = $message;
                return true;
            });
            try {
                $stream = stream_socket_client(
                    $conn,
                    $errorNumber,
                    $errorString,
                    $this->config->connectTimeout,
                    STREAM_CLIENT_CONNECT,
                    $streamContext,
                );
            } finally {
                restore_error_handler();
            }

            if ($stream !== false) {
                $this->stream = $stream;
                stream_set_timeout($this->stream, $this->config->readTimeout);
                if (function_exists('stream_set_read_buffer')) {
                    stream_set_read_buffer($this->stream, 0);
                }
                stream_set_write_buffer($this->stream, 0);

                $this->connected = true;
                $this->secure = match ($this->config->secure) {
                    SecureMode::Ssl, SecureMode::Tlsv1 => true,
                    default => false,
                };

                if ($this->secure) {
                    $this->capturePeerCertificate();
                }

                $this->emit(new ConnectionEstablished(
                    'Connection established',
                    [
                        'host' => $this->config->host,
                        'port' => $this->config->port,
                        'secure' => $this->secure,
                        'retries' => $attempt,
                    ],
                ));

                return;
            }

            $lastErrorNumber = $errorNumber;
            $lastErrorString = $errorString;

            // Only retry on transient "problem initializing the socket" (error code 0).
            if ($errorNumber !== 0) {
                break;
            }
        }

        $this->emit(new ConnectionFailed(
            'Connection failed',
            [
                'host' => $this->config->host,
                'port' => $this->config->port,
                'errorCode' => $lastErrorNumber,
                'errorMessage' => $lastErrorString,
            ],
        ));

        $e = new ConnectionException('Error connecting to server.');
        $e->details = sprintf('[%u] %s', $lastErrorNumber, $lastErrorString);
        throw $e;
    }

    /**
     * Ensure the stream resource is valid.
     *
     * @throws StreamException
     */
    private function requireStream(): void
    {
        if (!is_resource($this->stream)) {
            throw new StreamException('Not connected');
        }
    }

    /**
     * Capture the negotiated TLS peer certificate for channel binding.
     */
    private function capturePeerCertificate(): void
    {
        $params = stream_context_get_params($this->stream);
        $this->peerCertificate = $params['options']['ssl']['peer_certificate'] ?? null;
    }

    /**
     * The hash algorithm to fingerprint the peer certificate with, per
     * RFC 5929 §4.1: use the certificate's own signature digest, unless
     * that digest is MD5/SHA-1 (or unrecognized), in which case fall back
     * to SHA-256.
     *
     * @param array<string, mixed> $parsed Result of openssl_x509_parse().
     */
    private function fingerprintAlgorithm(array $parsed): string
    {
        $signatureType = $parsed['signatureTypeSN'] ?? $parsed['signatureTypeLN'] ?? '';
        if (is_string($signatureType) && preg_match('/sha(224|256|384|512)/i', $signatureType, $matches) === 1) {
            return 'sha' . $matches[1];
        }

        return 'sha256';
    }

    private function emit(object $event): void
    {
        $this->dispatcher?->dispatch($event);
    }
}
