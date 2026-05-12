# SDK Extension System Documentation

The Extension system allows developers to add custom API endpoints to the PostNL SDK while leveraging the SDK's built-in infrastructure including HTTP transport (with full middleware stack), authentication, logging, retries, and optional caching.

Extensions are ideal for:
- Integrating with PostNL APIs not yet covered by the SDK
- Creating reusable custom service wrappers
- Prototyping new API integrations
- Adding organization-specific API functionality

## Table of Contents

- [Prerequisites](#prerequisites)
- [Quick Start](#quick-start)
- [Closure-Based Extensions](#closure-based-extensions)
- [Class-Based Extensions](#class-based-extensions)
- [Using ConfigurableAction](#using-configurableaction)
- [Credential Strategies](#credential-strategies)
- [Using Cache Adapters](#using-cache-adapters)
- [Error Handling](#error-handling)
- [Validation and Extension IDs](#validation-and-extension-ids)
- [Best Practices](#best-practices)
- [Complete Examples](#complete-examples)

---

## Prerequisites

### SDK Setup

See the main SDK setup guide in the root documentation:
[SDK Root Documentation](../../README.md)

### Requirements

- PHP 8.2+
- An initialized `Postnl` client instance

---

## Quick Start

The SDK supports two extension approaches: **closure-based** (quick, inline) and **class-based** (reusable, testable).

### Closure-Based (Inline)

```php
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Service\Checkout\V1\Response\PostalCodeAddressResponse;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

// Register an inline extension
$postnl->extensions()->register('postal-code-check', fn(ServiceContext $context) => new ConfigurableAction(
    context: $context,
    endpoint: '/shipment/checkout/v1/postalcodecheck',
    httpMethod: HttpMethod::GET,
    credentialStrategy: CredentialStrategy::NONE,
    responseFactory: static fn(ResponseInterface $r) => new PostalCodeAddressResponse($r),
));

// Resolve the extension explicitly
$action = $postnl->extensions()->getAs('postal-code-check', ConfigurableAction::class);
$response = $action->execute($payload);
```

### Class-Based (Reusable)

```php
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Service\Checkout\V1\Extension\PostalCodeCheckExtension;

// Register a class-based extension
$postnl->extensions()->register('postal-code-check', PostalCodeCheckExtension::class);

// Resolve the typed extension explicitly
$action = $postnl->extensions()->getAs('postal-code-check', CacheableConfigurableAction::class);
$response = $action->execute($payload);
```

---

## Closure-Based Extensions

Closure-based extensions are ideal for quick prototypes, simple integrations, and development/testing scenarios.

### Closure Signature

```php
fn(ServiceContext $context): object
```

### Parameters

| Parameter | Type | Description |
|-----------|------|-------------|
| `$context` | `ServiceContext` | SDK context exposing `transport`, `apiVersion`, `identity`, `logger`, `cache`, and `payloadMapper` |

Closures should accept the current `ServiceContext` and return the extension service object.

### Code Example

```php
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Service\Checkout\V1\Response\PostalCodeAddressResponse;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

$postnl = Postnl::factory()->withAuth(Auth::fromEnv())->make();

// Register a closure-based extension
$postnl->extensions()->register('postal-code-check', fn(ServiceContext $context) => new ConfigurableAction(
    context: $context,
    endpoint: '/shipment/checkout/v1/postalcodecheck',
    httpMethod: HttpMethod::GET,
    credentialStrategy: CredentialStrategy::NONE,
    responseFactory: static fn(ResponseInterface $r) => new PostalCodeAddressResponse($r),
));

// Use the extension
$action = $postnl->extensions()->getAs('postal-code-check', ConfigurableAction::class);
$response = $action->execute($payload);
```

---

## Class-Based Extensions

Class-based extensions are recommended for production code, reusable components, and scenarios requiring unit testing.

### ExtensionInterface Contract

```php
use Postnl\Sdk\Extension\ExtensionInterface;
use Postnl\Sdk\Service\ServiceContext;

interface ExtensionInterface
{
    /**
     * Create the extension service instance.
     *
     * @param ServiceContext $context SDK context (transport, apiVersion, identity, cache, logger)
     * @return object The service instance (user-defined type)
     */
    public function create(ServiceContext $context): object;
}
```

### Implementation Example

```php
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Extension\ExtensionInterface;
use Postnl\Sdk\Service\Checkout\V1\Response\PostalCodeAddressResponse;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

class PostalCodeCheckExtension implements ExtensionInterface
{
    public function create(ServiceContext $context): object
    {
        $action = new ConfigurableAction(
            context: $context,
            endpoint: '/shipment/checkout/v1/postalcodecheck',
            httpMethod: HttpMethod::GET,
            credentialStrategy: CredentialStrategy::NONE,
            responseFactory: static fn(ResponseInterface $r) => new PostalCodeAddressResponse($r),
        );

        // Wrap with CacheableConfigurableAction for opt-in caching support
        return new CacheableConfigurableAction($action, $context->cache);
    }
}
```

> **Note:** Wrapping with `CacheableConfigurableAction` enables opt-in caching via the `cache($ttl)` method. Without calling `cache()`, requests execute normally without caching.

### Registration and Usage

```php
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;

$postnl = Postnl::factory()->withAuth(Auth::fromEnv())->make();

// Register the extension
$postnl->extensions()->register('postal-code-check', PostalCodeCheckExtension::class);

$action = $postnl->extensions()->getAs('postal-code-check', CacheableConfigurableAction::class);

// Use the extension without caching
$response = $action->execute($payload);

// Use the extension with caching (TTL in seconds)
$response = $action->cache(3600)->execute($payload);

// Use indefinite caching (TTL = 0)
$response = $action->cache(0)->execute($payload);
```

### Returning Custom Service Classes

Extensions can return any object type, not just `ConfigurableAction`. For complex APIs with multiple endpoints, return a custom service class:

```php
use Postnl\Sdk\Extension\ExtensionInterface;
use Postnl\Sdk\Service\ServiceContext;

class TrackingExtension implements ExtensionInterface
{
    public function create(ServiceContext $context): object
    {
        return new TrackingService($context);
    }
}

class TrackingService
{
    public function __construct(
        private ServiceContext $context
    ) {}

    public function getStatus(string $barcode): TrackingResponse
    {
        // Implementation using $this->context->transport
    }

    public function getHistory(string $barcode): TrackingHistoryResponse
    {
        // Implementation using $this->context->transport
    }
}
```

---

## Using ConfigurableAction

`ConfigurableAction` is a utility class that simplifies creating single API endpoint calls without writing a dedicated action class.

### Constructor Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `context` | `ServiceContext` | Yes | - | SDK infrastructure context (transport, identity, cache, etc.) |
| `endpoint` | `string` | Yes | - | API endpoint path (e.g., `/shipment/delivery/v4/track`) |
| `httpMethod` | `HttpMethod` | No | `POST` | HTTP method enum (`GET`, `POST`, `PUT`, `DELETE`) |
| `credentialStrategy` | `CredentialStrategy` | No | `ROOT` | How credentials are merged into payload |
| `responseFactory` | `Closure(ResponseInterface): ApiResponseInterface` | Yes | - | Factory to wrap PSR-7 response into API response |

### The execute() Method

```php
public function execute(RequestPayloadInterface $payload): ApiResponseInterface
```

The `execute()` method sends the request and returns the response produced by the configured `responseFactory`.

### Code Examples

#### GET Request

```php
use Postnl\Sdk\Support\PayloadMapper;
use Psr\Http\Message\ResponseInterface;

$action = new ConfigurableAction(
    context: $context,
    endpoint: '/shipment/checkout/v1/postalcodecheck',
    httpMethod: HttpMethod::GET,
    credentialStrategy: CredentialStrategy::NONE,
    responseFactory: static fn(ResponseInterface $r) => new CheckoutResponse($r),
);

$mapper  = PayloadMapper::create();
$payload = PostalCodeCheckRequest::fromArray([
    'postalcode'   => '2521CA',
    'housenumber'  => '3',
], $mapper);

$response = $action->execute($payload);
```

#### POST Request

```php
$action = new ConfigurableAction(
    context: $context,
    endpoint: '/shipment/v4/label',
    httpMethod: HttpMethod::POST,
    credentialStrategy: CredentialStrategy::ROOT,
    responseFactory: static fn(ResponseInterface $r) => new LabelResponse($r),
);

$response = $action->execute($labelPayload);
```

---

## Credential Strategies

The `CredentialStrategy` enum controls how `customerCode` and `customerNumber` are automatically merged into request payloads.

### Available Strategies

| Strategy | Enum Value | Description |
|----------|------------|-------------|
| `ROOT` | `CredentialStrategy::ROOT` | Merge credentials at payload root level |
| `INTO_SENDER` | `CredentialStrategy::INTO_SENDER` | Merge into the `sender` nested object |
| `INTO_RECEIVER` | `CredentialStrategy::INTO_RECEIVER` | Merge into the `receiver` nested object |
| `NONE` | `CredentialStrategy::NONE` | Do not merge credentials (caller must provide) |

### Usage

```php
use Postnl\Sdk\Enums\CredentialStrategy;

// Credentials merged at root: { "customerCode": "...", "customerNumber": "...", ... }
$action = new ConfigurableAction(
    // ...
    credentialStrategy: CredentialStrategy::ROOT,
);

// Credentials merged into sender: { "sender": { "customerCode": "...", ... } }
$action = new ConfigurableAction(
    // ...
    credentialStrategy: CredentialStrategy::INTO_SENDER,
);

// No credential merging (use for APIs that don't require credentials in payload)
$action = new ConfigurableAction(
    // ...
    credentialStrategy: CredentialStrategy::NONE,
);
```

### When to Use Each Strategy

| Strategy | Use Case |
|----------|----------|
| `ROOT` | Most common. APIs expecting flat credentials at payload root. |
| `INTO_SENDER` | Labelling/Confirming APIs with sender object structure. |
| `INTO_RECEIVER` | Return shipment APIs with receiver object structure. |
| `NONE` | APIs that don't require credentials in payload, or when handling credentials manually. |

---

## Using Cache Adapters

Extensions automatically receive the configured cache adapter from the `Postnl` client. This allows extensions to cache API responses for improved performance.

### Configuring Cache on the Client

#### Using CacheConfig Object

Create a `CacheConfig`, resolve it to a concrete adapter via `CacheFactory::create()`, and pass it to the builder with `withCache()`:

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Cache\CacheConfig;
use Postnl\Sdk\Cache\CacheFactory;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\CacheStoreType;

$cache = CacheFactory::create(new CacheConfig(
    cacheStoreType: CacheStoreType::REDIS,
    redisHost: '127.0.0.1',
    redisPort: 6379,
    defaultTtl: 3600,
    prefix: 'sdk_postnl_',
));

$postnl = Postnl::factory()
    ->withAuth(Auth::apiKey('your-api-key'))
    ->withCache($cache)
    ->make();
```

#### Using fromArray Configuration

```php
$config = ClientConfig::fromArray([
    'authenticationType' => 'apiKey',
    'apiKey' => 'your-api-key',
    'cache' => [
        'cacheStoreType' => 'redis',  // 'auto', 'redis', 'memcached', 'file', 'array'
        'redisHost' => '127.0.0.1',
        'redisPort' => 6379,
        'defaultTtl' => 3600,
    ],
]);

$postnl = PostnlFacade::sandboxClient('your-api-key');
```

#### Using Environment Variables

```bash
# Cache configuration
SDK_POSTNL_CACHE_STORE_TYPE=redis   # auto, redis, memcached, file, array
SDK_POSTNL_CACHE_TTL=3600
SDK_POSTNL_CACHE_PREFIX=sdk_postnl_

# Redis configuration
SDK_POSTNL_REDIS_HOST=127.0.0.1
SDK_POSTNL_REDIS_PORT=6379
SDK_POSTNL_REDIS_PASSWORD=
SDK_POSTNL_REDIS_DATABASE=0
```

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;

$postnl = Postnl::factory()->withAuth(Auth::fromEnv())->make();
```

### Available Cache Store Types

| Type | Description |
|------|-------------|
| `auto` | Auto-detect best available backend (Redis > Memcached > File > Array) |
| `redis` | Redis backend (requires `phpredis` extension) |
| `memcached` | Memcached backend (requires `memcached` extension) |
| `file` | File-based persistent cache |
| `array` | In-memory cache (request-scoped, useful for testing) |

### Using Cache in Extensions

The cache adapter is available on the `ServiceContext` passed to extensions (`$context->cache`).

#### Closure-Based Extension with Cache

```php
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

// Access cache via $context->cache; wrap with CacheableConfigurableAction for opt-in caching
$postnl->extensions()->register('postal-code-check', fn(ServiceContext $context) => new CacheableConfigurableAction(
    new ConfigurableAction(
        context: $context,
        endpoint: '/shipment/checkout/v1/postalcodecheck',
        httpMethod: HttpMethod::GET,
        credentialStrategy: CredentialStrategy::NONE,
        responseFactory: static fn(ResponseInterface $r) => new CheckoutResponse($r),
    ),
    $context->cache
));
```

#### Class-Based Extension with Cache

```php
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Extension\ExtensionInterface;
use Postnl\Sdk\Service\Checkout\V1\Response\PostalCodeAddressResponse;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

class PostalCodeCheckExtension implements ExtensionInterface
{
    public function create(ServiceContext $context): object
    {
        $action = new ConfigurableAction(
            context: $context,
            endpoint: '/shipment/checkout/v1/postalcodecheck',
            httpMethod: HttpMethod::GET,
            credentialStrategy: CredentialStrategy::NONE,
            responseFactory: static fn(ResponseInterface $r) => new PostalCodeAddressResponse($r),
        );

        return new CacheableConfigurableAction($action, $context->cache);
    }
}

// Usage with caching
$action = $postnl->extensions()->getAs('postal-code-check', CacheableConfigurableAction::class);
$response = $action->cache(3600)->execute($payload);
```

### Custom Caching in Services

For custom service classes, you can implement your own caching logic using the cache adapter from the context:

```php
use Postnl\Sdk\Service\ServiceContext;

class CachedTrackingService
{
    private const CACHE_PREFIX = 'tracking_';

    public function __construct(
        private ServiceContext $context
    ) {}

    public function getStatus(string $barcode): array
    {
        $cacheKey = self::CACHE_PREFIX . $barcode;
        $cache = $this->context->cache;

        // Try to get from cache
        if ($cache !== null) {
            $cached = $cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        // Cache miss - fetch from API
        $result = $this->fetchFromApi($barcode);

        // Store in cache (TTL: 5 minutes)
        if ($cache !== null) {
            $cache->set($cacheKey, $result, 300);
        }

        return $result;
    }
}
```

### Cache Adapter Methods (PSR-16)

| Method | Description |
|--------|-------------|
| `get(string $key, mixed $default = null)` | Retrieve value or return default |
| `set(string $key, mixed $value, int\|null $ttl = null)` | Store value with optional TTL |
| `has(string $key)` | Check if key exists |
| `delete(string $key)` | Delete a single key |
| `clear()` | Clear all cached entries |

---

## Error Handling

### Exception Types

| Exception | When Thrown |
|-----------|-------------|
| `SdkInvalidArgumentException` | Empty extension ID, invalid extension ID, invalid factory class, or `getAs()` type mismatch / missing expected class |
| `UnknownExtensionException` | Resolving an unregistered extension ID |
| `SdkRuntimeException` | Factory execution failure or factory returns non-object |

### Common Validation Errors

| Error Scenario | Message |
|----------------|---------|
| Empty ID | `Extension ID cannot be empty.` |
| Invalid ID | `Extension ID "Tracking" must contain only lowercase letters, digits, ".", "_" or "-".` |
| Non-existent class | `Extension class "Foo\Bar" does not exist.` |
| Missing interface | `Extension class "stdClass" must implement ExtensionInterface.` |
| Type mismatch | `Extension "tracking" resolved to stdClass, expected App\TrackingService.` |

### Example Error Handling

```php
use Postnl\Sdk\Exception\InvalidArgumentSdkException;
use Postnl\Sdk\Exception\UnknownExtensionException;
use Postnl\Sdk\Exception\RuntimeSdkException;
use Postnl\Sdk\Exception\PostnlSdkException;

try {
    $postnl->extensions()->register('tracking', TrackingExtension::class);
    $tracking = $postnl->extensions()->getAs('tracking', TrackingService::class);
    $response = $tracking->getStatus($request);

} catch (InvalidArgumentSdkException $e) {
    // Registration or type validation failed
    echo "Argument error: " . $e->getMessage();

} catch (UnknownExtensionException $e) {
    // Extension not registered
    echo "Extension not found: " . $e->getMessage();

} catch (RuntimeSdkException $e) {
    // Factory execution failed
    echo "Factory error: " . $e->getMessage();

} catch (PostnlSdkException $e) {
    // API request failed
    echo "API error: " . $e->getMessage();
}
```

---

## Validation and Extension IDs

### Extension ID Rules

- ID cannot be empty
- ID must contain only lowercase letters, digits, `.`, `_`, or `-`
- IDs do not share the main client method namespace, so built-in service methods never collide with extension IDs

### Factory Validation

- **Closures**: Expected shape is `fn(ServiceContext $context): object`
- **Class strings**: Must exist and implement `ExtensionInterface`

---

## Best Practices

### Naming Conventions

- Use lowercase IDs such as `tracking`, `address-validation`, or `postal-code-check`
- Be descriptive but concise (for example `bulk-shipment` over `bs`)
- Avoid generic names like `api`, `service`, or `custom`

### Performance

- Extensions are **lazily resolved** (created only when first called)
- Extensions are **cached as singletons** per `Postnl` instance
- Re-registering an extension clears the cached instance
- Use the cache adapter for repetitive API calls

### Registration Lifecycle

- Register extensions during application bootstrap, service provider setup, or container wiring
- Avoid registering extensions inside request handlers when the client is shared as a singleton
- Laravel: register extensions in a service provider after constructing the shared client instance
- Symfony: register extensions in the service definition or bundle extension that wires the client

### Testing

```php
use Postnl\Sdk\Service\ServiceContext;

// Unit testing class-based extensions
public function testExtensionCreatesConfigurableAction(): void
{
    $context = $this->createMock(ServiceContext::class);

    $extension = new PostalCodeCheckExtension();
    $result = $extension->create($context);

    $this->assertInstanceOf(ConfigurableAction::class, $result);
}
```

### Retry Opt-Out for Non-Idempotent Endpoints

POST requests are retried by default because most PostNL POST endpoints are effectively idempotent
(e.g. label generation returns the same label for the same payload within a session). If your
endpoint has side effects that must not be duplicated (e.g. creating a new return shipment record
on every call), pass `retryable: false`:

```php
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Service\ReturnShipment\V4\Response\GenerateReturnResponse;
use Postnl\Sdk\Service\ServiceContext;
use Psr\Http\Message\ResponseInterface;

$postnl->extensions()->register('generate-return', fn(ServiceContext $context) => new ConfigurableAction(
    context: $context,
    endpoint: '/shipment/delivery/v4/return/generate',
    httpMethod: HttpMethod::POST,
    credentialStrategy: CredentialStrategy::ROOT,
    retryable: false,   // non-idempotent — each call creates a new return shipment record
    responseFactory: static fn(ResponseInterface $r) => new GenerateReturnResponse($r),
));
```

When `retryable: false`, the transport calls the endpoint exactly once and propagates any exception
directly without entering the retry loop.

### Security

- Use `CredentialStrategy::NONE` when credentials should not be automatically merged
- Validate user input before passing to extension methods
- Do not expose `TransportInterface` instances publicly

---

## Complete Examples

### Example 1: Postal Code Check (From SDK)

This example shows the built-in `PostalCodeCheckExtension`:

```php
use Postnl\Sdk\Action\ConfigurableAction;
use Postnl\Sdk\Action\CacheableConfigurableAction;
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\CredentialStrategy;
use Postnl\Sdk\Enums\HttpMethod;
use Postnl\Sdk\Exception\PostnlSdkException;
use Postnl\Sdk\RequestData\V1\PostalCodeCheckRequest;
use Postnl\Sdk\Service\Checkout\V1\Extension\PostalCodeCheckExtension;
use Postnl\Sdk\Service\Checkout\V1\Response\PostalCodeAddressResponse;
use Postnl\Sdk\Service\ServiceContext;
use Postnl\Sdk\Support\PayloadMapper;
use Psr\Http\Message\ResponseInterface;

$postnl = Postnl::factory()->withAuth(Auth::fromEnv())->make();

try {
    // Create request payload
    $mapper  = PayloadMapper::create();
    $payload = PostalCodeCheckRequest::fromArray([
        'postalcode'          => '2521CA',
        'housenumber'         => '3',
        'housenumberaddition' => 'bis',
    ], $mapper);

    // Option 1: Class-based extension
    $postnl->extensions()->register('postal-code-check', PostalCodeCheckExtension::class);
    $action = $postnl->extensions()->getAs('postal-code-check', CacheableConfigurableAction::class);

    $response = $action->execute($payload);

    // Option 2: Closure-based extension (inline)
    $postnl->extensions()->register('postal-code-check', fn(ServiceContext $context) => new ConfigurableAction(
        context: $context,
        endpoint: '/shipment/checkout/v1/postalcodecheck',
        httpMethod: HttpMethod::GET,
        credentialStrategy: CredentialStrategy::NONE,
        responseFactory: static fn(ResponseInterface $r) => new PostalCodeAddressResponse($r),
    ));

    $action = $postnl->extensions()->getAs('postal-code-check', ConfigurableAction::class);
    $response = $action->execute($payload);

    // Process response
    if ($response->isSuccess()) {
        echo json_encode($response->meta()->toArray(), JSON_PRETTY_PRINT);
    }

} catch (PostnlSdkException $e) {
    echo "Error: " . $e->getMessage();
}
```

### Example 2: Custom Tracking Service (Multi-Action)

This example shows a class-based extension returning a custom service with multiple methods:

```php
use Postnl\Sdk\Extension\ExtensionInterface;
use Postnl\Sdk\Service\ServiceContext;

// Extension class
class TrackingExtension implements ExtensionInterface
{
    public function create(ServiceContext $context): object
    {
        return new TrackingService($context);
    }
}

// Custom service class
class TrackingService
{
    public function __construct(
        private ServiceContext $context
    ) {}

    public function getStatus(string $barcode): array
    {
        // Use $this->context->transport to make API calls
        // ...
    }

    public function getFullHistory(string $barcode): array
    {
        // Use $this->context->transport to make API calls
        // ...
    }
}

// Usage
$postnl->extensions()->register('tracking', TrackingExtension::class);

$trackingService = $postnl->extensions()->getAs('tracking', TrackingService::class);
$status = $trackingService->getStatus('3SDEVC123456789');
$history = $trackingService->getFullHistory('3SDEVC123456789');
```

### Example 3: Bootstrap Registration

Register extensions once during application bootstrap, then resolve them explicitly where needed:

```php
// Bootstrap time
$postnl
    ->extensions()
    ->register('postal-code-check', PostalCodeCheckExtension::class)
    ->register('tracking', TrackingExtension::class)
    ->register('address-validation', AddressValidationExtension::class);

// Later in request handling / application services
$postalCodeCheck = $postnl->extensions()->getAs('postal-code-check', CacheableConfigurableAction::class);
$tracking = $postnl->extensions()->getAs('tracking', TrackingService::class);
$validation = $postnl->extensions()->getAs('address-validation', AddressValidationService::class);

$postalCodeResult = $postalCodeCheck->execute($payload);
$trackingResult = $tracking->getStatus($barcode);
$validationResult = $validation->validate($address);
```
