# Error Handling

The SDK uses structured exceptions aligned with [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457). Every HTTP error response is parsed into a `ProblemDetails` DTO and wrapped in a semantic exception class, giving you typed access to status codes, validation errors, trace IDs, and retry information.

[SDK Root Documentation](../../README.md)

---

## Table of Contents

- [Exception Hierarchy](#exception-hierarchy)
- [Horizontal Marker Interfaces](#horizontal-marker-interfaces)
- [HTTP Exceptions](#http-exceptions)
- [ProblemDetails (RFC 9457)](#problemdetails-rfc-9457)
- [Validation Errors](#validation-errors)
- [Retry Behavior](#retry-behavior)
- [Transport Exceptions](#transport-exceptions)
- [Schema Mismatch Exceptions](#schema-mismatch-exceptions)
- [SDK Exceptions](#sdk-exceptions)
- [Exception Hierarchy Reference](#exception-hierarchy-reference)
- [Catch Patterns](#catch-patterns)

---

## Exception Hierarchy

```
PostnlExceptionInterface (marker — catch-all for any SDK exception)
├── AuthExceptionInterface       (horizontal marker — any auth failure)
├── TransportExceptionInterface  (horizontal capability — pre-response transport failure; exposes getFailureReason())
├── ClientErrorExceptionInterface (horizontal marker — any HTTP 4xx)
├── ServerErrorExceptionInterface (horizontal marker — server-side failure or schema break)
│
├── PostnlSdkException (extends RuntimeException)
│   ├── HttpSdkException (abstract — all HTTP error responses)
│   │   ├── Client\ValidationException         (400, 422)  [ClientErrorExceptionInterface]
│   │   ├── Client\AuthenticationException      (401, 403)  [AuthExceptionInterface, ClientErrorExceptionInterface]
│   │   ├── Client\ClientException              (other 4xx) [ClientErrorExceptionInterface]
│   │   └── RetryableHttpSdkException (abstract — retryable HTTP errors)
│   │       ├── Client\RateLimitException       (429)       [ClientErrorExceptionInterface]
│   │       ├── Client\TimeoutException         (408)       [ClientErrorExceptionInterface]
│   │       └── Server\ServerException          (5xx, Cloudflare 521-524) [ServerErrorExceptionInterface]
│   ├── Transport\TransportException            (network failures, always retryable) [TransportExceptionInterface]
│   ├── SchemaMismatchException                 (API contract break) [ServerErrorExceptionInterface]
│   ├── Retry\RetryExhaustedException           (all retries exhausted)
│   └── SdkRuntimeException                    (internal SDK errors)
├── SdkLogicException (extends LogicException — programmer errors)
└── SdkInvalidArgumentException (extends InvalidArgumentException)
```

All exceptions implement `PostnlExceptionInterface`, so `catch (PostnlExceptionInterface $e)` covers every exception the SDK can throw.

### Auth\AuthException

`AuthException` also implements `AuthExceptionInterface`.

---

## Horizontal Marker Interfaces

Three empty marker interfaces and one capability interface allow `catch`-by-intent without listing every concrete class.
`TransportExceptionInterface` is a **capability interface** (declares `getFailureReason(): TransportFailureReason`); the other three are pure markers with no methods.

| Interface | Implemented by | Use when |
|-----------|---------------|----------|
| `AuthExceptionInterface` | `AuthException`, `AuthenticationException` | Any auth failure (pre-request or HTTP 401/403) |
| `TransportExceptionInterface` | `TransportException` | Pre-response network failure (DNS, TLS, etc.) |
| `ClientErrorExceptionInterface` | `ClientException`, `AuthenticationException`, `ValidationException`, `RateLimitException`, `TimeoutException` | Any HTTP 4xx |
| `ServerErrorExceptionInterface` | `ServerException`, `SchemaMismatchException` | Server-side failure or API schema break |

All four extend `PostnlExceptionInterface`, so they remain within the SDK exception boundary.

```php
use Postnl\Sdk\Exception\AuthExceptionInterface;
use Postnl\Sdk\Exception\ClientErrorExceptionInterface;
use Postnl\Sdk\Exception\ServerErrorExceptionInterface;
use Postnl\Sdk\Exception\TransportExceptionInterface;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (AuthExceptionInterface $e) {
    // Refresh credentials, page auth oncall
} catch (TransportExceptionInterface $e) {
    // Check $e->getFailureReason() for DNS/TLS/timeout details
} catch (ServerErrorExceptionInterface $e) {
    // PostNL is down or returned unrecognisable data
} catch (ClientErrorExceptionInterface $e) {
    // Caller-side error; inspect and fix the request
}
```

---

## HTTP Exceptions

Every HTTP error response is mapped to a concrete `HttpSdkException` subclass based on the status code:

| Status Code | Exception Class | Retryable |
|-------------|-----------------|-----------|
| 400 Bad Request | `ValidationException` | No |
| 401 Unauthorized | `AuthenticationException` | No |
| 403 Forbidden | `AuthenticationException` | No |
| 408 Request Timeout | `TimeoutException` | Yes |
| 422 Unprocessable Entity | `ValidationException` | No |
| 429 Too Many Requests | `RateLimitException` | Yes |
| Other 4xx | `ClientException` | No |
| 500, 502, 503, 504 | `ServerException` | Yes |
| 501 Not Implemented | `ServerException` | No |
| 521-524 (Cloudflare) | `ServerException` | Yes |

### Properties

All `HttpSdkException` subclasses expose:

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$status` | `?HttpStatus` | Typed enum value, or `null` for non-standard codes |
| `$statusCode` | `int` | Raw HTTP status code (always present) |
| `$problemDetails` | `ProblemDetails` | Parsed response body (see below) |
| `getRequest()` | `RequestInterface` | The PSR-7 request that failed |
| `getResponse()` | `ResponseInterface` | The PSR-7 response received |
| `getCode()` | `int` | Same as `$statusCode` |
| `getMessage()` | `string` | Human-readable error message |

### String Representation

```
[HTTP 400] POST https://api.postnl.nl/v4/shipment - Invalid postal code (traceId: abc-123)
```

---

## ProblemDetails (RFC 9457)

Every `HttpSdkException` carries a `ProblemDetails` instance that normalizes the API error response, regardless of format.

### Properties

| Property | Type | Description |
|----------|------|-------------|
| `$type` | `?string` | RFC 9457 problem type URI |
| `$title` | `?string` | Short human-readable summary |
| `$status` | `?int` | HTTP status code (echoed from body) |
| `$detail` | `?string` | Occurrence-specific explanation |
| `$instance` | `?string` | URI identifying this specific occurrence |
| `$traceId` | `?string` | PostNL correlation ID for server-side tracing |
| `$fieldErrors` | `list<FieldError>` | Normalized validation errors |
| `$faults` | `list<Fault>` | Legacy fault entries |
| `$extensions` | `array<string, mixed>` | Any extra response body fields |
| `$rawBody` | `string` | Original response body |

### Methods

| Method | Returns | Description |
|--------|---------|-------------|
| `getMessage()` | `string` | Best-effort message (priority: detail → title → first fault → "Unknown error") |
| `hasFieldErrors()` | `bool` | Whether any field-level validation errors exist |
| `getFieldError(string $field)` | `?FieldError` | Get the first error for a specific field |
| `hasFaults()` | `bool` | Whether any legacy fault entries exist |

### Supported API Error Formats

`ProblemDetails` automatically normalizes all PostNL API error formats:

- **RFC 9457**: `{ "type": "...", "title": "...", "status": 400, "detail": "...", "errors": { "field": ["msg"] } }`
- **Legacy fault**: `{ "fault": { "faultstring": "...", "detail": {} } }`
- **Legacy faults array**: `{ "faults": [{ "faultstring": "..." }] }`
- **Barcode errors**: `{ "errors": [{ "code": "x", "description": "y" }] }`
- **Checkout V1 errors**: `{ "errors": [{ "status": 400, "title": "Bad Request", "detail": "..." }] }`
- **Generic message**: `{ "message": "..." }`
- **Plain text body**: Non-JSON responses are stored in `$detail`

### Example

```php
use Postnl\Sdk\Exception\HttpSdkException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (HttpSdkException $e) {
    $details = $e->problemDetails;

    echo $details->getMessage();       // Human-readable error
    echo $details->traceId;            // PostNL correlation ID
    echo $details->type;               // RFC 9457 problem type URI

    // Access raw body for debugging
    echo $details->rawBody;
}
```

---

## Validation Errors

`ValidationException` is thrown for **400 Bad Request** and **422 Unprocessable Entity** responses. It exposes a convenience `$fieldErrors` property containing structured validation errors.

### FieldError

Each `FieldError` has:

| Property | Type | Description |
|----------|------|-------------|
| `$field` | `string` | The field name that failed validation |
| `$message` | `string` | Human-readable error message |
| `$code` | `?string` | Optional error code |

### Example

```php
use Postnl\Sdk\Exception\Client\ValidationException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (ValidationException $e) {
    foreach ($e->fieldErrors as $error) {
        echo "Field '{$error->field}': {$error->message}\n";
    }

    // Or look up a specific field
    $postalCodeError = $e->problemDetails->getFieldError('postalCode');
    if ($postalCodeError !== null) {
        echo "Postal code error: {$postalCodeError->message}\n";
    }
}
```

---

## Retry Behavior

The SDK automatically retries requests that fail with retryable exceptions when retry is configured. Retryable exceptions implement `RetryableExceptionInterface`:

| Exception | Retryable | Retry-After Header |
|-----------|-----------|--------------------|
| `RateLimitException` (429) | Always | Yes, parsed from response |
| `TimeoutException` (408) | Always | Yes, parsed from response |
| `ServerException` (5xx) | Depends on status code | Yes, parsed from response |
| `TransportException` (network) | Always | No (no response received) |

### RetryableExceptionInterface

```php
interface RetryableExceptionInterface
{
    public function isRetryable(): bool;
    public function retryAfterSeconds(): ?int;
}
```

### RetryExhaustedException

When all retry attempts are exhausted, `RetryExhaustedException` is thrown. The last failure is available via `getPrevious()`. Every failure (including the final attempt) is recorded in `$failedAttempts`, so you can inspect the full chain (not only the final exception).

| Property / Method | Type | Description |
|-------------------|------|-------------|
| `$attempts` | `int` | Total number of attempts made |
| `$lastResponse` | `?ResponseInterface` | The last response received (if any) |
| `$failedAttempts` | `list<array{attempt: int, exception: PostnlSdkException}>` | Each failed attempt: one-based attempt index and the exception thrown |
| `getPrevious()` | `?Throwable` | The exception from the final failed attempt (same as the last entry in `$failedAttempts` when non-empty) |

### Example

```php
use Postnl\Sdk\Exception\Retry\RetryExhaustedException;
use Postnl\Sdk\Exception\HttpSdkException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (RetryExhaustedException $e) {
    echo "Failed after {$e->attempts} attempts\n";

    foreach ($e->failedAttempts as $entry) {
        echo "  Attempt {$entry['attempt']}: " . $entry['exception']->getMessage() . "\n";
    }

    // Access the final failure (same as the last failedAttempts entry)
    $original = $e->getPrevious();
    if ($original instanceof HttpSdkException) {
        echo "Last error: " . $original->getMessage() . "\n";
    }
}
```

---

## Transport Exceptions

`TransportException` represents non-HTTP transport failures: DNS resolution, TLS handshake, connection refused, network timeouts, etc.

- Always retryable (implements `RetryableExceptionInterface`)
- No `Retry-After` delay available (no HTTP response was received)
- Implements PSR-18 `NetworkExceptionInterface`
- Implements `TransportExceptionInterface` (horizontal capability interface — exposes `getFailureReason()` for catch-by-intent without downcasting)
- Access the failed request via `getRequest()`
- `$failureReason` provides a best-effort classification of the failure (see `TransportFailureReason`)

### TransportFailureReason

`TransportException::$failureReason` is a `TransportFailureReason` enum (default: `Unknown`) populated by `ExceptionNormalizer` based on the underlying PSR-18 exception:

| Case | Value | Description |
|------|-------|-------------|
| `ConnectionRefused` | `connection_refused` | TCP refused / network unreachable |
| `DnsFailure` | `dns_failure` | Hostname resolution failed |
| `TlsHandshake` | `tls_handshake` | SSL/TLS negotiation failed |
| `SocketTimeout` | `socket_timeout` | Connect or read timeout expired |
| `Unknown` | `unknown` | Transport gave insufficient detail to classify |

Classification is best-effort. `Unknown` is a first-class valid value when the transport layer does not expose classifiable details.

### String Representation

```
[Transport] GET https://api.postnl.nl/v4/shipment - Connection refused
```

### Example

```php
use Postnl\Sdk\Enums\TransportFailureReason;
use Postnl\Sdk\Exception\Transport\TransportException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (TransportException $e) {
    echo "Transport error: " . $e->getMessage() . "\n";
    echo "Request: " . $e->getRequest()->getUri() . "\n";

    // Inspect the failure reason for targeted alerting
    match ($e->failureReason) {
        TransportFailureReason::DnsFailure       => log("DNS lookup failed — check network config"),
        TransportFailureReason::TlsHandshake     => log("TLS error — check certificate chain"),
        TransportFailureReason::SocketTimeout    => log("Connect timeout — PostNL may be slow"),
        TransportFailureReason::ConnectionRefused => log("Connection refused — check firewall"),
        TransportFailureReason::Unknown          => log("Unknown transport error"),
    };
}
```

---

## Schema Mismatch Exceptions

`SchemaMismatchException` is thrown when PostNL returns a response that does not conform to the SDK's expected schema — a required field is absent or a field value cannot be cast to the required type. This is a PostNL API contract break, not a programmer error.

- Extends `PostnlSdkException` (runtime exception root)
- Implements `ServerErrorExceptionInterface` (PostNL returned something the SDK cannot parse)
- Carries `$targetClass` and `$field` public readonly properties for structured logging

| Property | Type | Description |
|----------|------|-------------|
| `$targetClass` | `string` | The payload class that could not be hydrated |
| `$field` | `string` | The payload key of the offending field |

### Named Constructors

| Method | Thrown when |
|--------|-------------|
| `SchemaMismatchException::missingField($class, $field)` | A required (non-nullable, no default) field is absent from the response |
| `SchemaMismatchException::typeMismatch($class, $field, $expected, $actual)` | A field value cannot be coerced to the required type |

### Example

```php
use Postnl\Sdk\Exception\SchemaMismatchException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (SchemaMismatchException $e) {
    // PostNL returned an incompatible response — page SDK maintainers
    log("Schema mismatch in {$e->targetClass} field {$e->field}: " . $e->getMessage());
}
```

---

## SDK Exceptions

These exceptions are thrown for programming errors and SDK internal failures, not HTTP responses:

| Exception | Base Class | When Thrown |
|-----------|------------|------------|
| `SdkRuntimeException` | `RuntimeException` | Internal SDK errors |
| `SdkLogicException` | `LogicException` | Programmer errors (e.g., invalid builder usage) |
| `SdkInvalidArgumentException` | `InvalidArgumentException` | Invalid arguments passed to SDK methods |

These are documented in their respective service guides:
- Builder validation: [Configuration](../Configuration/README.md)
- Extension errors: [Extension](../Extension/README.md)

---

## Catch Patterns

### Simple — Catch All SDK Exceptions

```php
use Postnl\Sdk\Exception\PostnlExceptionInterface;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (PostnlExceptionInterface $e) {
    echo "SDK error: " . $e->getMessage() . "\n";
}
```

### Capability-based — Horizontal Markers

```php
use Postnl\Sdk\Exception\AuthExceptionInterface;
use Postnl\Sdk\Exception\ClientErrorExceptionInterface;
use Postnl\Sdk\Exception\SchemaMismatchException;
use Postnl\Sdk\Exception\ServerErrorExceptionInterface;
use Postnl\Sdk\Exception\TransportExceptionInterface;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (AuthExceptionInterface $e) {
    // Refresh creds, page auth oncall
} catch (TransportExceptionInterface $e) {
    // Check $e->getFailureReason() for DNS/TLS/timeout
} catch (SchemaMismatchException $e) {
    // API contract break — page SDK maintainers
} catch (ServerErrorExceptionInterface $e) {
    // PostNL is down or returned unrecognisable data
} catch (ClientErrorExceptionInterface $e) {
    // Caller-side error; typically don't retry
}
```

### Standard — HTTP-Aware

```php
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\HttpSdkException;
use Postnl\Sdk\Exception\Transport\TransportException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
} catch (ValidationException $e) {
    // 400, 422 — inspect field errors
    foreach ($e->fieldErrors as $error) {
        echo "  {$error->field}: {$error->message}\n";
    }

} catch (AuthenticationException $e) {
    // 401, 403 — check credentials
    echo "Authentication failed: " . $e->getMessage() . "\n";

} catch (HttpSdkException $e) {
    // All other HTTP errors (4xx, 5xx)
    echo "[{$e->statusCode}] " . $e->getMessage() . "\n";

} catch (TransportException $e) {
    // Network failure — no HTTP response
    echo "Network error: " . $e->getMessage() . "\n";
}
```

### Production — Full Coverage

```php
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\RateLimitException;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\HttpSdkException;
use Postnl\Sdk\Exception\Retry\RetryExhaustedException;
use Postnl\Sdk\Exception\SchemaMismatchException;
use Postnl\Sdk\Exception\Transport\TransportException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);

} catch (ValidationException $e) {
    foreach ($e->fieldErrors as $error) {
        log("Validation: {$error->field} — {$error->message}");
    }

} catch (AuthenticationException $e) {
    log("Auth failed: " . $e->getMessage());

} catch (RateLimitException $e) {
    $retryAfter = $e->retryAfterSeconds();
    log("Rate limited, retry after {$retryAfter}s");

} catch (RetryExhaustedException $e) {
    log("Gave up after {$e->attempts} attempts: " . $e->getPrevious()?->getMessage());
    foreach ($e->failedAttempts as $entry) {
        log("Attempt {$entry['attempt']}: " . $entry['exception']->getMessage());
    }

} catch (HttpSdkException $e) {
    log("[HTTP {$e->statusCode}] " . $e->getMessage());
    if ($e->problemDetails->traceId !== null) {
        log("Trace ID: " . $e->problemDetails->traceId);
    }

} catch (TransportException $e) {
    log("Network error ({$e->failureReason->value}): " . $e->getMessage());

} catch (SchemaMismatchException $e) {
    log("API schema break — {$e->targetClass}::{$e->field}: " . $e->getMessage());
}
```

---

## Exception Hierarchy Reference

```
PostnlExceptionInterface (marker — catch-all for any SDK exception)
├── PostnlSdkException (extends RuntimeException)
│   ├── HttpSdkException (abstract — all HTTP error responses)
│   │   ├── Client\ValidationException         (400, 422)
│   │   ├── Client\AuthenticationException      (401, 403)
│   │   ├── Client\ClientException              (other 4xx)
│   │   └── RetryableHttpSdkException (abstract — retryable HTTP errors)
│   │       ├── Client\RateLimitException       (429)
│   │       ├── Client\TimeoutException         (408)
│   │       └── Server\ServerException          (5xx, Cloudflare 521-524)
│   ├── Transport\TransportException            (network failures, always retryable)
│   ├── SchemaMismatchException                 (API contract break) [ServerErrorExceptionInterface]
│   ├── Retry\RetryExhaustedException           (all retries exhausted)
│   └── SdkRuntimeException                    (internal SDK errors)
├── SdkLogicException (extends LogicException — programmer errors)
└── SdkInvalidArgumentException (extends InvalidArgumentException)
```

All exceptions implement `PostnlExceptionInterface`, so `catch (PostnlExceptionInterface $e)` covers every exception the SDK can throw.

---

