# Configuration

## Environment Variables

`Auth::fromEnv()` loads **authentication** from environment variables. All other settings (retry policy, API version, cache, etc.) are configured via fluent builder methods on `ClientBuilder`.

### Authentication (required)

| Variable | Required | Description |
|----------|----------|-------------|
| `SDK_POSTNL_API_KEY` | Yes (if using API key) | PostNL API key |
| `SDK_POSTNL_CLIENT_ID` | Yes (if using OAuth) | OAuth client ID |
| `SDK_POSTNL_CLIENT_SECRET` | Yes (if using OAuth) | OAuth client secret |
| `SDK_POSTNL_OAUTH_TOKEN_URL` | Yes (if using OAuth) | OAuth token endpoint URL |
| `SDK_POSTNL_IS_SANDBOX` | No | Set to `true` for sandbox mode (default: `false`) |

### Cache (optional)

| Variable | Description |
|----------|-------------|
| `SDK_POSTNL_CACHE_STORE_TYPE` | Backend: `array`, `redis`, `memcached`, `file` (default: `auto`) |
| `SDK_POSTNL_CACHE_TTL` | Default TTL in seconds (default: `3600`) |
| `SDK_POSTNL_CACHE_PREFIX` | Key prefix (default: `sdk_postnl_`) |
| `SDK_POSTNL_REDIS_HOST` | Redis host (enables Redis when set) |
| `SDK_POSTNL_REDIS_PORT` | Redis port (default: `6379`) |
| `SDK_POSTNL_REDIS_PASSWORD` | Redis password |
| `SDK_POSTNL_REDIS_DATABASE` | Redis database index (default: `0`) |
| `SDK_POSTNL_MEMCACHED_HOST` | Memcached host (enables Memcached when set) |
| `SDK_POSTNL_MEMCACHED_PORT` | Memcached port (default: `11211`) |
| `SDK_POSTNL_FILE_CACHE_DIR` | Directory for file-based cache |
| `SDK_POSTNL_LOGGER_CLASS_PATH` | FQCN of a PSR-3 logger to use for cache logging |

### Operational (optional)

| Variable | Description |
|----------|-------------|
| `SDK_POSTNL_MAX_RETRIES` | Maximum retry attempts (default: `3`, set `0` to disable) |
| `SDK_POSTNL_RETRY_DELAY_MS` | Base retry delay in milliseconds (default: `1000`) |
| `SDK_POSTNL_MAX_RETRY_DELAY_MS` | Maximum retry delay cap in milliseconds (default: `10000`) |
| `SDK_POSTNL_SOURCE_SYSTEM` | Source system identifier header |
| `SDK_POSTNL_API_VERSION` | API version (`1`, `4`, or `5`; default: `4`) |
| `SDK_POSTNL_CUSTOMER_NUMBER` | Customer number |
| `SDK_POSTNL_CUSTOMER_CODE` | Customer code |
| `SDK_POSTNL_MIN_LOG_LEVEL` | Minimum log level (`debug`, `info`, `warning`, `error`, etc.) |
| `SDK_POSTNL_LOGGER_CLASS_PATH` | FQCN of a PSR-3 logger class to instantiate |

All settings can be configured via fluent methods on the `ClientBuilder` returned by `Postnl::factory()`.

## HTTP Client

The SDK uses [PSR-18](https://www.php-fig.org/psr/psr-18/) for HTTP. No specific client is bundled — `php-http/discovery` auto-detects whichever PSR-18 client your project already has installed.

**Precedence:** If you call both `withHttpClient()` and `withGuzzleOptions()`, the client passed to `withHttpClient()` is used and Guzzle options are ignored.

### Zero-config (auto-discovery)

Install any PSR-18 client and the SDK will find it automatically:

```bash
# Guzzle (most common)
composer require guzzlehttp/guzzle

# Or Symfony HTTP Client
composer require symfony/http-client nyholm/psr7
```

Then build the client normally — no extra configuration needed:

```php
use Postnl\Sdk\Postnl;
use Postnl\Sdk\Auth\Auth;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->make();
```

### Guzzle options (most common case)

When Guzzle is installed, use `withGuzzleOptions()` to configure timeouts, SSL verification, and debug output without instantiating Guzzle yourself. The SDK creates the Guzzle client and applies its own retry logic.

```php
use Postnl\Sdk\Postnl;
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Config\GuzzleClientOptions;

$options = new GuzzleClientOptions(
    timeout: 30.0,
    connectTimeout: 10.0,
    verify: true,
    debug: false,
);

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withGuzzleOptions($options)
    ->make();
```

Or use defaults:

```php
$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withGuzzleOptions(GuzzleClientOptions::defaults())
    ->make();
```

If you call `withGuzzleOptions()` but Guzzle is not installed, `make()` throws `SdkLogicException`. Install Guzzle (`composer require guzzlehttp/guzzle`) or use `withHttpClient()` with another PSR-18 client.

### Custom PSR-18 client

Pass your own pre-configured PSR-18 client via `withHttpClient()`. This bypasses auto-discovery entirely and gives you full control (timeouts, SSL verification, proxy, etc.):

```php
use Postnl\Sdk\Postnl;
use Postnl\Sdk\Auth\Auth;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\RequestOptions;

$httpClient = new GuzzleClient([
    RequestOptions::TIMEOUT         => 30,
    RequestOptions::CONNECT_TIMEOUT => 10,
    RequestOptions::VERIFY          => false, // disable SSL for internal networks
]);

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withHttpClient($httpClient)
    ->make();
```

When you pass a custom HTTP client, the SDK **does not** wrap the transport with its own retry logic (to avoid double retries). If you need retries, configure them on your client or implement retry in your application.

### HTTP Plugins (PSR-18 agnostic)

Use `withPlugin()` to intercept requests without any Guzzle dependency. Plugins implement `HttpPluginInterface` and work with any PSR-18 client:

```php
use Postnl\Sdk\Transport\HttpPluginInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class CustomHeaderPlugin implements HttpPluginInterface
{
    public function handleRequest(RequestInterface $request, callable $next): ResponseInterface
    {
        return $next($request->withHeader('X-Custom', 'value'));
    }
}

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withPlugin(new CustomHeaderPlugin())
    ->make();
```

### HTTP-layer response caching

Use `CachingPlugin::create()` together with `withPlugin()` to enable response caching for selected endpoint URI patterns. The named constructor performs PSR-17 factory discovery automatically:

```php
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Transport\Cache\CachingPlugin;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withLogger($logger)
    ->withPlugin(CachingPlugin::create(
        cache:            $yourPsr16Cache,
        ttl:              3600,
        allowedEndpoints: ['/timeframe/', '/locations/'],
        logger:           $logger,
    ))
    ->make();
```

If you want cache read/write failures to be logged, pass `logger: $logger` explicitly to `CachingPlugin::create()`, typically using the same logger instance you passed to `withLogger()`. The caching plugin does not inherit the client logger automatically.

Auth headers are excluded from the cache key, so OAuth token rotation never causes unnecessary cache misses.

**Multi-tenant deployments:** When multiple API keys share the same cache backend, pass a unique `$keyPrefix` per tenant to prevent cross-tenant cache collisions:

```php
->withPlugin(CachingPlugin::create(
    cache:            $sharedCache,
    allowedEndpoints: ['/locations/'],
    keyPrefix:        'sdk_postnl_http_tenant-a_',
))
```

Pass `withPlugin()` multiple times to register independent caching plugins for different endpoint groups:

```php
->withPlugin(CachingPlugin::create(cache: $cache, allowedEndpoints: ['/locations/']))
->withPlugin(CachingPlugin::create(cache: $cache, allowedEndpoints: ['/timeframe/']))
```

## Log Redaction

The SDK automatically redacts sensitive data from all log output. No configuration is required — the default rules cover the most common cases.

### How it works

`RedactionRegistry` maintains a flat, case-insensitive key→strategy map built from a hardcoded `DEFAULT_MAP`. `LogSanitizer` recursively walks decoded request/response arrays and redacts values whose key matches a registered strategy.

### Redaction strategies

| Strategy | Result | Use case |
|----------|--------|----------|
| `RedactionStrategy::FullMask` | `***REDACTED***` | Addresses, house numbers, secrets, tokens |
| `RedactionStrategy::PartialMask` | `foo********com` (fixed-width hidden block) | Email addresses, names, postal codes |
| `RedactionStrategy::BinaryContentOmit` | `[CONTENT OMITTED]` | Label PDFs, binary signatures |

### Default sensitive fields

The following fields are redacted automatically (case-insensitive key matching, applied at any nesting depth):

**Contact / address** — `email`, `firstname`, `lastname`, `mobilenumber`, `phonenumber` → `PartialMask`; `housenumber`, `postalcode`, `street`, `addressline`, `doorcode`, `insuredvalue`, `description` → `FullMask`; `label`, `mergedlabel`, `labelsignature` → `BinaryContentOmit`

**Credential defaults** — `authorization`, `cookie`, `set-cookie`, `password`, `token`, `secret` → `FullMask`; `apikey`, `clientid`, `clientsecret` → `PartialMask`

### Customising the registry

```php
use Postnl\Sdk\Logger\Redaction\RedactionRegistry;
use Postnl\Sdk\Logger\Redaction\RedactionStrategy;

$registry = new RedactionRegistry();               // starts with all defaults
$registry->addStrategy('myField', RedactionStrategy::FullMask);
$registry->removeStrategy('description');          // remove a default rule

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withRedactionRegistry($registry)
    ->make();
```

Start from a blank slate with `RedactionRegistry::empty()`:

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Logger\Redaction\RedactionRegistry;
use Postnl\Sdk\Logger\Redaction\RedactionStrategy;

$registry = RedactionRegistry::empty();
$registry->addStrategy('apiKey', RedactionStrategy::PartialMask);

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withRedactionRegistry($registry)
    ->make();
```

### Disabling redaction entirely

Pass a `NullRedactionRegistry` to emit raw data to logs without any masking:

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Logger\Redaction\NullRedactionRegistry;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withRedactionRegistry(new NullRedactionRegistry())
    ->make();
```

## Custom Payload Mapper

The SDK ships with a default `PayloadMapper` that handles hydration and normalisation of all request and response objects. To replace it — for example, to register domain-specific type casters or alternative reflection strategies — implement `PayloadMapperInterface` and pass it to `withPayloadMapper()`:

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Support\Contracts\PayloadMapperInterface;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withPayloadMapper(new MyCustomPayloadMapper())
    ->make();
```

When `withPayloadMapper()` is not called, the SDK automatically creates a `PayloadMapper::create()` instance per client. The mapper is stored in `ServiceContext::$payloadMapper` and threaded through every `fromArray()` / `toArray()` call in the request and response pipeline.

When building request payloads with `fromArray()` outside the SDK pipeline (for example in tests or CLI scripts), create a mapper instance directly:

```php
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = SomeRequest::fromArray($data, $mapper);
```

---

## Recommended Usage

### API Key Authentication

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\Version;
use Postnl\Sdk\Transport\Retry\ExponentialBackoffRetryPolicy;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withRetryPolicy(new ExponentialBackoffRetryPolicy(maxRetries: 3, baseDelayMs: 1000, maxDelayMs: 10000))
    ->withApiVersion(Version::V4)
    ->make();
```

### OAuth Authentication

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Transport\Retry\ExponentialBackoffRetryPolicy;

$client = Postnl::factory()
    ->withAuth(Auth::fromEnv())
    ->withRetryPolicy(new ExponentialBackoffRetryPolicy(maxRetries: 3, baseDelayMs: 1000, maxDelayMs: 10000))
    ->make();
```

### Fully Programmatic (No Environment Variables)

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\ClientBuilder;
use Postnl\Sdk\Enums\Version;
use Postnl\Sdk\Transport\Retry\ExponentialBackoffRetryPolicy;

$client = (new ClientBuilder())
    ->withAuth(Auth::apiKey('your-api-key'))
    ->withSandbox(true)
    ->withRetryPolicy(new ExponentialBackoffRetryPolicy(maxRetries: 3, baseDelayMs: 1000, maxDelayMs: 10000))
    ->withApiVersion(Version::V4)
    ->make();
```

## Error Handling

`make()` fails fast with clear error messages:

- **No auth configured**: Throws when `make()` is called without a prior `withAuth()` call.
- **Auth already configured**: Throws if `withAuth()` is called twice on the same builder.
- **Both auth types in env**: `Auth::fromEnv()` throws if both `SDK_POSTNL_API_KEY` and OAuth env vars are set.
- **Partial OAuth in env**: `Auth::fromEnv()` throws if only some OAuth env vars are set, listing the missing ones.
- **No PSR-18 client found**: Throws `SdkLogicException` if `make()` is called and no PSR-18 client is installed. Fix: `composer require guzzlehttp/guzzle`.
- **Guzzle options set but Guzzle not installed**: Throws `SdkLogicException` if `withGuzzleOptions()` was used but `guzzlehttp/guzzle` is not installed. Fix: `composer require guzzlehttp/guzzle` or use `withHttpClient()` with another PSR-18 client.
