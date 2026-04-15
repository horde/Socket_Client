# Upgrading Socket_Client

## Version 3.0.0

### PHP Version

Socket_Client 3.0.0 requires PHP 8.1 or higher (was 7.4).

### New Implementation (`src/`)

Version 3.0.0 adds a modern, fully typed implementation in the `Horde\Socket\Client\` namespace (`src/`). The legacy `Horde\Socket\Client` class in `lib/` is unchanged and continues to work — the two codepaths are independent. Migrate at your own pace.

| Horde 5 (lib/) | Horde 6 (src/) |
|---|---|
| `Horde\Socket\Client` | `Horde\Socket\Client\Client` |
| `Horde\Socket\Client\Exception` | `Horde\Socket\Client\Exception\SocketException` |
| N/A | `Horde\Socket\Client\ClientInterface` |
| N/A | `Horde\Socket\Client\ConnectionConfig` |
| N/A | `Horde\Socket\Client\SecureMode` |
| N/A | `Horde\Socket\Client\StreamStatus` |

### Breaking Changes

#### Constructor Replaced by ConnectionConfig DTO

The positional parameter list and `$params` array bag are replaced by an immutable configuration object.

**Before (lib/):**
```php
use Horde\Socket\Client;

$client = new Client(
    'imap.example.com',
    993,
    30,
    'ssl',
    ['ssl' => ['cafile' => '/etc/ssl/certs/ca-bundle.crt']],
    ['debug' => true]
);
```

**After (src/):**
```php
use Horde\Socket\Client\Client;
use Horde\Socket\Client\ConnectionConfig;
use Horde\Socket\Client\SecureMode;

$config = new ConnectionConfig(
    host: 'imap.example.com',
    port: 993,
    secure: SecureMode::Ssl,
    caFile: '/etc/ssl/certs/ca-bundle.crt',
);
$client = new Client($config);
```

#### Secure Mode is a Backed Enum

The mixed `$secure` parameter (string, bool, false) is replaced by the `SecureMode` enum.

| Legacy value | `SecureMode` case |
|---|---|
| `false` | `SecureMode::None` |
| `'ssl'` | `SecureMode::Ssl` |
| `'tls'` | `SecureMode::Tls` |
| `'tlsv1'` | `SecureMode::Tlsv1` |
| `'sslv2'` / `'sslv3'` | Removed (insecure, dropped by PHP) |
| `true` | Removed (use an explicit case) |

#### Property Access Replaced by Methods

**Before (lib/):**
```php
$client->connected;
$client->secure;
```

**After (src/):**
```php
$client->isConnected();
$client->isSecure();
```

#### getStatus() Returns a Value Object

**Before (lib/):**
```php
$status = $client->getStatus();
if ($status['timed_out']) { ... }
if ($status['eof']) { ... }
```

**After (src/):**
```php
$status = $client->getStatus();
if ($status->timedOut) { ... }
if ($status->eof) { ... }
// Also: $status->blocked, $status->unreadBytes
```

#### Typed Exception Hierarchy

**Before (lib/):**
```php
use Horde\Socket\Client\Exception;

try {
    $client = new \Horde\Socket\Client('host', 993);
} catch (Exception $e) {
    // single exception type for everything
}
```

**After (src/):**
```php
use Horde\Socket\Client\Exception\ConnectionException;
use Horde\Socket\Client\Exception\StreamException;
use Horde\Socket\Client\Exception\TimeoutException;
use Horde\Socket\Client\Exception\SocketException;

try {
    $client = new Client($config);
    $client->write($data);
} catch (ConnectionException $e) {
    // connect/retry failures
} catch (TimeoutException $e) {
    // read/write timeout
} catch (StreamException $e) {
    // I/O on closed/broken stream
} catch (SocketException $e) {
    // catch-all base class
}
```

#### Port is Required

The legacy constructor accepted `$port = null`. `ConnectionConfig` requires an explicit `int $port`.

#### Peer Verification is ON by Default

The legacy implementation disabled `verify_peer` and `verify_peer_name` by default. The modern implementation enables both. To restore the old behaviour:

```php
$config = new ConnectionConfig(
    host: 'mail.example.com',
    port: 993,
    verifyPeer: false,
    verifyPeerName: false,
);
```

### New Features

#### Separate Connect and Read Timeouts

The legacy `$timeout` parameter controlled both timeouts. `ConnectionConfig` splits them:

```php
$config = new ConnectionConfig(
    host: 'mail.example.com',
    port: 993,
    connectTimeout: 10,
    readTimeout: 120,
);
```

#### Configurable Retry Count

The legacy implementation hardcoded 3 retries for transient socket initialisation errors. `ConnectionConfig::$maxRetries` makes this explicit:

```php
$config = new ConnectionConfig(
    host: 'mail.example.com',
    port: 993,
    maxRetries: 5,
);
```

#### PSR-14 Event Dispatching (Optional)

The new `Client` accepts an optional `Psr\EventDispatcher\EventDispatcherInterface` as its second constructor parameter. When `null` (the default), no events are dispatched and no additional packages are required.

```php
use Psr\EventDispatcher\EventDispatcherInterface;
use Horde\Socket\Client\Client;
use Horde\Socket\Client\ConnectionConfig;

// $dispatcher is any PSR-14 implementation
$client = new Client($config, $dispatcher);
```

To use events, install the interface package and a concrete implementation:

```bash
composer require psr/event-dispatcher:^1 horde/eventdispatcher:^1
```

Events emitted:

| Event class | When |
|---|---|
| `Event\ConnectionEstablished` | After a successful socket connection |
| `Event\ConnectionFailed` | After all retry attempts are exhausted |
| `Event\ConnectionClosed` | After `close()` is called |
| `Event\TlsNegotiated` | After `startTls()` succeeds |
| `Event\TlsFailed` | After `startTls()` fails |

All events extend `Event\SocketEvent` and provide `getMessage(): string` and `getContext(): array` with structured data (host, port, error details, etc.).

#### ClientInterface

Type-hint against `ClientInterface` instead of the concrete class:

```php
use Horde\Socket\Client\ClientInterface;

class ImapTransport
{
    public function __construct(
        private readonly ClientInterface $socket,
    ) {}
}
```

### Comparison Table

| Feature | Socket_Client 2.x (lib/) | Socket_Client 3.x (src/) |
|---|---|---|
| PHP Version | 7.4+ | 8.1+ |
| Namespace | `Horde\Socket\Client` | `Horde\Socket\Client\Client` |
| Configuration | Positional params + array | `ConnectionConfig` DTO |
| Security mode | String / bool | `SecureMode` enum |
| Status return | Raw array | `StreamStatus` value object |
| Exceptions | Single class | Typed hierarchy |
| Peer verification | Off by default | On by default |
| Timeouts | Single value | Separate connect/read |
| Retry count | Hardcoded 3 | Configurable |
| Event dispatching | N/A | Optional PSR-14 |
| Error handling | `@` suppression | Scoped error handlers |
| Type declarations | None | Full strict types |
