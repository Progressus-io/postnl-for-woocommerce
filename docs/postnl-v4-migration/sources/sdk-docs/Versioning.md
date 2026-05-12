# API Versioning & Deprecation

The PostNL PHP SDK supports multiple API versions via the `Version` enum (`V1`, `V4`).
All current service implementations target **V4**, which is the recommended version for all integrations.

## Why V1 is deprecated

`Version::V1` was an early API version for which no service implementations exist.
It is marked `#[DeprecatedVersion(since: '1.0', migrateToVersion: 'V4')]` on the enum constant.
Any attempt to create a service for V1 will emit a PHP `E_USER_DEPRECATED` error and a PSR-3 warning, pointing you to V4.

## Migrating to V4

Replace any `Version::V1` references in your code by configuring the client to use `Version::V4`.
`Version::V4` is the default, so no explicit `withApiVersion()` call is required in most cases.

```php
use Postnl\Sdk\Auth\Auth;
use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\Version;

// Before (deprecated): code that attempted to use Version::V1
// would receive a LogicSdkException (no V1 implementations exist),
// now preceded by an E_USER_DEPRECATED notice pointing to V4.

// After — Version::V4 is the default; withApiVersion() is shown here
// only to make the migration intent explicit.
$client = Postnl::factory()
    ->withAuth(Auth::apiKey('your-api-key'))
    ->withApiVersion(Version::V4)
    ->make();

$client->singleTimeframe()->getSingleServiceTimeframe($payload);
```

## Handling `E_USER_DEPRECATED` notices in test suites

When running your tests you may see output like:

```
PostNL SDK: API version "V1" is deprecated since 1.0. Migrate to version "V4".
```

To suppress these in PHPUnit you can register a custom error handler in your `setUp()`:

```php
protected function setUp(): void
{
    set_error_handler(static fn() => true, E_USER_DEPRECATED);
}

protected function tearDown(): void
{
    restore_error_handler();
}
```

Or, in `phpunit.xml`, configure `<source>` and `failOnDeprecation="false"` as needed.

## Registering per-service deprecations

> **Note:** This is an advanced, internal-extension pattern intended for framework integrations
> and SDK forks. `ServiceFactory` is the internal service registry (see [CLAUDE.md](../CLAUDE.md)).
> This surface is **not** covered by the semver-stable public API guarantee.

SDK integrators can register custom per-service deprecation entries at runtime only when working
with `ServiceFactory` directly. Call `deprecateVersion()` on your own factory instance in your
application bootstrapping layer or other custom integration code.

This is **not supported via `Postnl::factory()` / `ClientBuilder`**. The builder creates and owns
its internal `ServiceFactory`, and `withService()` is only for registering interface-to-class
mappings, not for injecting a `ServiceFactory` instance or per-service deprecation metadata.

```php
use Postnl\Sdk\Enums\Version;
use Postnl\Sdk\Service\Deprecation\DeprecationNotifier;
use Postnl\Sdk\Service\ServiceFactory;
use Postnl\Sdk\Service\SingleServiceTimeframe\SingleServiceTimeframeInterface;

$factory = new ServiceFactory(notifier: new DeprecationNotifier($logger));

$factory->deprecateVersion(
    interface: SingleServiceTimeframeInterface::class,
    version: Version::V1,
    since: '2024-01',
    migrateToVersion: 'V4',
    message: 'V1 timeframe endpoint removed from sandbox. Switch to V4 immediately.',
);
```

When `ServiceFactory::create()` is called for that interface+version, the per-service message fires
instead of the global enum attribute message — preventing double notifications and enabling targeted guidance.

## Introspecting deprecation metadata at runtime

You can read the `#[DeprecatedVersion]` attribute from any `Version` case:

```php
$dep = Version::V1->deprecation();
// $dep->since            → '1.0'
// $dep->migrateToVersion → 'V4'
// $dep->message          → ''

$dep = Version::V4->deprecation();
// null — V4 is not deprecated
```
