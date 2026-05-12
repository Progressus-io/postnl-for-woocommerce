# Barcode API Documentation

The Barcode functionality allows you to generate barcodes for PostNL shipments. Barcodes are unique identifiers used to track parcels throughout the shipping process.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Generate Barcode](#generate-barcode)
- [Data Models](#data-models)
- [Collection Methods](#collection-methods)
- [Error Handling](#error-handling)
- [Complete Example](#complete-example)

---

## Prerequisites

### SDK Setup

See the main SDK setup guide in the root documentation:
➡️ [SDK Root Documentation](../../README.md)

---

### Required Credentials

All Barcode requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

---

## Generate Barcode

Generate one or more barcodes for shipments using a specified series range.

### Endpoint

```
POST /shipment/delivery/v4/barcode
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `customerNumber` | string | No | Your PostNL customer number |
| `customerCode` | string | No | Your PostNL customer code |
| `seriesStart` | string | No | Start of barcode series range (e.g., `000000000`) |
| `seriesEnd` | string | No | End of barcode series range (e.g., `999999999`) |
| `numberOfBarcodes` | int | No | Number of barcodes to generate |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Service\Barcode\V4\Request\BarcodeRequest;

$request = new BarcodeRequest(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    serieStart: '000000000',
    serieEnd: '999999999',
    numberOfBarcodes: 5,
);

$response = $postnl->barcode()->generateBarcode($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\Service\Barcode\V4\Request\BarcodeRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = BarcodeRequest::fromArray([
    'customerNumber'   => 'YOUR_CUSTOMER_NUMBER',
    'customerCode'     => 'YOUR_CUSTOMER_CODE',
    'seriesStart'      => '000000000',
    'seriesEnd'        => '999999999',
    'numberOfBarcodes' => 5,
], $mapper);

$response = $postnl->barcode()->generateBarcode($request);
```

### API Response Structure

```json
{
    "barcodes": [
        "3SDEVC123456789",
        "3SDEVC123456790",
        "3SDEVC123456791",
        "3SDEVC123456792",
        "3SDEVC123456793"
    ]
}
```

### Working with the Response

```php
$response = $postnl->barcode()->generateBarcode($request);

// Get the collection of barcodes
$collection = $response->barcodes();

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Get total count
echo "Generated " . $collection->count() . " barcodes\n";

// Get first barcode
$firstBarcode = $collection->first();
echo $firstBarcode->get();           // "3SDEVC123456789"
echo (string) $firstBarcode;          // "3SDEVC123456789"

// Check if barcode is international
echo $firstBarcode->isInternational(); // false

// Iterate over all barcodes
foreach ($collection as $barcode) {
    echo $barcode->get() . "\n";
}

// Get raw array response
$rawData = $response->meta()->toArray();
```

---

## Data Models

### BarcodeRequest

Represents the request payload for generating barcodes.

```php
readonly class BarcodeRequest
{
    public ?string $customerNumber;   // Your PostNL customer number
    public ?string $customerCode;     // Your PostNL customer code
    public ?string $seriesStart;      // Start of barcode series range
    public ?string $seriesEnd;        // End of barcode series range
    public ?int $numberOfBarcodes;    // Number of barcodes to generate
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `fromArray(array $data, PayloadMapperInterface $mapper)` | self | Create instance from associative array |
| `toArray(PayloadMapperInterface $mapper)` | array | Convert to array (null values filtered) |

### Barcode

Represents a single barcode value object.

```php
final readonly class Barcode
{
    public string $barcode;  // The barcode string
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `get()` | string | Returns the barcode string |
| `__toString()` | string | String conversion (same as `get()`) |
| `isInternational()` | bool | Returns `true` if barcode starts with 'LA' and ends with 'XNL' |

**Example Usage:**

```php
$barcode = $collection->first();

// Get barcode value
echo $barcode->get();        // "3SDEVC123456789"
echo (string) $barcode;       // "3SDEVC123456789"

// Check if international shipment
if ($barcode->isInternational()) {
    echo "This is an international barcode";
}
```

---

## Collection Methods

`BarcodesCollection` provides methods for working with barcode results:

### Base Methods

| Method | Return | Description |
|--------|--------|-------------|
| `count()` | int | Number of barcodes in collection |
| `isEmpty()` | bool | Returns `true` if collection is empty |
| `all()` | array | Returns all Barcode objects as array |
| `first()` | ?Barcode | Returns first barcode or null |
| `last()` | ?Barcode | Returns last barcode or null |
| `get(int $index)` | ?Barcode | Returns barcode at index or null |

### Filtering Methods

All filter methods return a new collection (immutable).

```php
// Filter to only international barcodes
$international = $collection->filter(function ($barcode) {
    return $barcode->isInternational();
});

// Filter barcodes starting with specific prefix
$filtered = $collection->filter(function ($barcode) {
    return str_starts_with($barcode->get(), '3SDEVC');
});
```

### Helper Methods

```php
// Find first matching barcode
$found = $collection->find(
    fn ($barcode) => $barcode->isInternational()
);

// Check if any barcodes are international
$hasInternational = $collection->some(
    fn ($barcode) => $barcode->isInternational()
);

// Check if all barcodes are domestic
$allDomestic = $collection->every(
    fn ($barcode) => !$barcode->isInternational()
);

// Map to array of strings
$barcodeStrings = $collection->map(
    fn ($barcode) => $barcode->get()
);
```

### Iteration

```php
// Iterate using foreach
foreach ($collection as $barcode) {
    echo $barcode->get() . "\n";
}

// Using iterator
$iterator = $collection->getIterator();
```

---

## Error Handling

The SDK throws semantic exceptions for validation and request errors.

> For the complete exception hierarchy, ProblemDetails, and retry behavior, see the [Error Handling guide](../ErrorHandling/README.md).

### Common Exception Types

The most common concrete exception types (non-exhaustive) are:

| Exception | Description |
|-----------|-------------|
| `ValidationException` | Invalid request parameters (400, 422) |
| `AuthenticationException` | Invalid or insufficient credentials (401, 403) |
| `ClientException` | Generic client error for other 4xx responses |
| `TimeoutException` | Request timeout (408, retry behavior depends on idempotency) |
| `RateLimitException` | Too many requests (429, retryable) |
| `ServerException` | Server error (5xx, retryable) |
| `HttpSdkException` | Base class for all HTTP errors |

### Common Validation Errors

| Error Scenario | Description |
|----------------|-------------|
| Invalid credentials | `customerCode` or `customerNumber` not valid |
| Invalid series range | `seriesStart` greater than `seriesEnd` |
| Excessive count | `numberOfBarcodes` exceeds allowed maximum |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\ValidationException;

try {
    $response = $postnl->barcode()->generateBarcode($request);
    $collection = $response->barcodes();

    foreach ($collection as $barcode) {
        echo $barcode->get() . "\n";
    }

} catch (ValidationException $e) {
    // Handle validation errors (400, 422)
    echo "Validation failed: " . $e->getMessage() . "\n";
    foreach ($e->fieldErrors as $error) {
        echo "  Field '{$error->field}': {$error->message}\n";
    }

} catch (AuthenticationException $e) {
    // Handle authentication/authorization errors (401, 403)
    echo "Authentication failed: " . $e->getMessage() . "\n";
}
```

---

## Complete Example

```php
<?php

use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Service\Barcode\V4\Request\BarcodeRequest;

// Initialize SDK
$postnl = Postnl::sandboxClient('your-api-key');

// Build request
$request = new BarcodeRequest(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    serieStart: '000000000',
    serieEnd: '999999999',
    numberOfBarcodes: 10,
);

try {
    $response = $postnl->barcode()->generateBarcode($request);
    $collection = $response->barcodes();

    // Check if successful
    if ($response->isSuccess()) {
        echo "Successfully generated {$collection->count()} barcodes\n\n";
    }

    // Process barcodes
    foreach ($collection as $index => $barcode) {
        $type = $barcode->isInternational() ? 'International' : 'Domestic';
        echo sprintf("[%d] %s (%s)\n", $index + 1, $barcode->get(), $type);
    }

    // Get specific barcode by index
    $thirdBarcode = $collection->get(2);
    if ($thirdBarcode !== null) {
        echo "\nThird barcode: " . $thirdBarcode->get() . "\n";
    }

    // Map to plain array of strings
    $barcodeStrings = $collection->map(fn ($b) => $b->get());
    echo "\nAll barcodes as array:\n";
    print_r($barcodeStrings);

} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
