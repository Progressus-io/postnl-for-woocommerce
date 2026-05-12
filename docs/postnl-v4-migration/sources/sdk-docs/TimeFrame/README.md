# TimeFrame API Documentation

The TimeFrame functionality allows you to retrieve available delivery timeframes for PostNL shipments. It supports both single service queries (one delivery option) and multiple services queries (comparing different delivery options).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Single Service Timeframe](#single-service-timeframe)
- [Multiple Services Timeframe](#multiple-services-timeframe)
- [Data Models](#data-models)
- [Collection Methods](#collection-methods)
- [Error Handling](#error-handling)
- [Enums Reference](#enums-reference)

---

## Prerequisites

### SDK Setup

See the main SDK setup guide in the root documentation:  
➡️ [SDK Root Documentation](../../README.md)

---

### Required Credentials

All TimeFrame requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

---

## Single Service Timeframe

Query delivery timeframes for a single service type (e.g., daytime or evening delivery).

### Endpoint

```
POST /shipment/delivery/v4/timeframe/singleservice
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `handoverDate` | string | Yes | Date when shipment is handed to PostNL (ISO 8601: `YYYY-MM-DD`) |
| `deliveryDays` | int | No | Number of delivery days to include (1-14, default varies) |
| `receiverAddress` | Address | Yes | Receiver address with `postalCode` and `countryIso` |
| `service` | string | Yes | Service type: `daytime` or `evening` |
| `shipmentType` | string | No | Shipment type: `parcel`, `letterbox`, `pallet`, `packet` |
| `customerCode` | string | Yes | Your PostNL customer code |
| `customerNumber` | string | Yes | Your PostNL customer number |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\ShipmentType;
use Postnl\Sdk\Enums\TimeFrameService;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Checkout\V4\Request\SingleServiceTimeframeRequest;

$request = new SingleServiceTimeframeRequest(
    handoverDate: date('Y-m-d', strtotime('+1 day')),
    deliveryDays: 7,
    receiverAddress: new Address(
        postalCode: '2595AA',
        countryIso: 'NL',
    ),
    service: TimeFrameService::Daytime->value,
    shipmentType: ShipmentType::Parcel->value,
    customerCode: 'YOUR_CUSTOMER_CODE',
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
);

$response = $postnl->checkout()->getSingleServiceTimeframe($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\Service\Checkout\V4\Request\SingleServiceTimeframeRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = SingleServiceTimeframeRequest::fromArray([
    'handoverDate'    => '2024-01-15',
    'deliveryDays'    => 7,
    'receiverAddress' => [
        'postalCode' => '2595AA',
        'countryIso' => 'NL',
    ],
    'service'         => 'daytime',
    'shipmentType'    => 'parcel',
    'customerCode'    => 'YOUR_CUSTOMER_CODE',
    'customerNumber'  => 'YOUR_CUSTOMER_NUMBER',
], $mapper);

$response = $postnl->checkout()->getSingleServiceTimeframe($request);
```

### API Response Structure

```json
{
    "deliveryDates": [
        {
            "deliveryDate": "2024-01-16",
            "timeFrame": {
                "from": "08:00:00",
                "until": "10:30:00"
            },
            "sustainability": {
                "code": "00",
                "description": "Not available"
            },
            "service": "daytime",
            "shipmentType": "parcel"
        },
        {
            "deliveryDate": "2024-01-17",
            "timeFrame": {
                "from": "08:30:00",
                "until": "21:30:00"
            },
            "sustainability": {
                "code": "02",
                "description": "Sustainable"
            },
            "service": "daytime",
            "shipmentType": "parcel"
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->checkout()->getSingleServiceTimeframe($request);

// Get the collection of timeframes
$collection = $response->timeframesSingleServiceCollection();

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Iterate over all timeframes
foreach ($collection as $timeframe) {
    echo $timeframe->deliveryDate;                    // "2024-01-16"
    echo $timeframe->service;                         // "daytime"
    echo $timeframe->shipmentType;                    // "parcel"
    echo $timeframe->timeFrame->from;                 // "08:00:00"
    echo $timeframe->timeFrame->until;                // "10:30:00"
    echo $timeframe->timeFrame->getFormattedRange(); // "08:00:00 - 10:30:00"
    echo $timeframe->sustainability->code;            // "00"
    echo $timeframe->sustainability->isSustainable(); // false
}

// Get raw array response
$rawData = $response->meta()->toArray();
```

---

## Multiple Services Timeframe

Query delivery timeframes for multiple service types simultaneously, allowing comparison between options.

### Endpoint

```
POST /shipment/delivery/v4/timeframe/multipleservices
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `handoverDate` | string | Yes | Date when shipment is handed to PostNL (ISO 8601: `YYYY-MM-DD`) |
| `numberOfDays` | int | No | Number of days to look ahead (1-14) |
| `receiverAddress` | Address | Yes | Receiver address with `postalCode` and `countryIso` |
| `services` | array | Yes | Array of service types: `['daytime', 'evening']` |
| `shipmentType` | string | No | Shipment type: `parcel`, `letterbox`, `pallet`, `packet` |
| `customerCode` | string | Yes | Your PostNL customer code |
| `customerNumber` | string | Yes | Your PostNL customer number |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\ShipmentType;
use Postnl\Sdk\Enums\TimeFrameService;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Checkout\V4\Request\MultipleServicesTimeframeRequest;

$request = new MultipleServicesTimeframeRequest(
    handoverDate: date('Y-m-d', strtotime('+1 day')),
    receiverAddress: new Address(
        postalCode: '2595AA',
        countryIso: 'NL',
    ),
    services: [
        TimeFrameService::Daytime->value,
        TimeFrameService::Evening->value,
    ],
    shipmentType: ShipmentType::Parcel->value,
    numberOfDays: 14,
    customerCode: 'YOUR_CUSTOMER_CODE',
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
);

$response = $postnl->checkout()->getMultipleServicesTimeframe($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\Service\Checkout\V4\Request\MultipleServicesTimeframeRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = MultipleServicesTimeframeRequest::fromArray([
    'handoverDate'    => '2024-01-15',
    'numberOfDays'    => 14,
    'receiverAddress' => [
        'postalCode' => '2595AA',
        'countryIso' => 'NL',
    ],
    'services'        => ['daytime', 'evening'],
    'shipmentType'    => 'parcel',
    'customerCode'    => 'YOUR_CUSTOMER_CODE',
    'customerNumber'  => 'YOUR_CUSTOMER_NUMBER',
], $mapper);

$response = $postnl->checkout()->getMultipleServicesTimeframe($request);
```

### API Response Structure

```json
{
    "deliveryDates": [
        {
            "deliveryDate": "2024-01-16",
            "services": [
                {
                    "service": "daytime",
                    "timeFrame": {
                        "from": "08:00:00",
                        "until": "12:00:00"
                    },
                    "sustainability": {
                        "code": "00",
                        "description": "Not available"
                    },
                    "shipmentType": "parcel",
                    "availability": true,
                    "reason": null
                },
                {
                    "service": "evening",
                    "timeFrame": {
                        "from": "18:00:00",
                        "until": "22:00:00"
                    },
                    "sustainability": {
                        "code": "02",
                        "description": "Sustainable"
                    },
                    "shipmentType": "parcel",
                    "availability": true,
                    "reason": null
                }
            ]
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->checkout()->getMultipleServicesTimeframe($request);

// Get the flattened collection of all timeframes
$collection = $response->timeframesMultipleServicesCollection();

// The collection flattens the nested structure
// Each item includes deliveryDate from parent level
foreach ($collection as $timeframe) {
    echo $timeframe->deliveryDate;    // "2024-01-16"
    echo $timeframe->service;         // "daytime" or "evening"
    echo $timeframe->isAvailable();   // true/false
    echo $timeframe->reason;          // null or reason string if unavailable
}

// Total count (services * days)
echo $collection->count(); // e.g., 28 for 2 services * 14 days
```

---

## Data Models

### SingleTimeFrame

Represents a single delivery timeframe option.

```php
readonly class SingleTimeFrame
{
    public ?string $deliveryDate;        // "2024-01-16"
    public ?TimeSlot $timeFrame;         // Time window object
    public ?Sustainability $sustainability; // Sustainability info
    public ?bool $availability;          // true if available (multiple services only)
    public ?string $reason;              // Reason if unavailable
    public ?string $service;             // "daytime" or "evening"
    public ?string $shipmentType;        // "parcel", "letterbox", etc.
}
```

**Methods:**

| Method / Property | Return | Description |
|-------------------|--------|-------------|
| `isAvailable()` | bool | Returns `true` if `availability === true` |
| `sustainability` | `?Sustainability` | Sustainability info; use `$timeframe->sustainability?->isSustainable()` to check if it is sustainable |

### TimeSlot

Represents the delivery time window.

```php
readonly class TimeSlot
{
    public ?string $from;   // "08:00:00"
    public ?string $until;  // "17:30:00"
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `getFormattedRange()` | string | Returns `"08:00:00 - 17:30:00"` |
| `isMorning()` | bool | Returns `true` if delivery until 12:00 |
| `isEvening()` | bool | Returns `true` if delivery from 17:00+ |
| `is24Hour()` | bool | Returns `true` if slot covers entire day |

### Sustainability

Represents sustainability information for the delivery option.

```php
readonly class Sustainability
{
    public ?string $code;         // "00", "01", "02", "03"
    public ?string $description;  // "Not available", "Carbon neutral", etc.
}
```

**Sustainability Codes:**

| Code | Description | Sustainable |
|------|-------------|-------------|
| `00` | Not available | No |
| `01` | Carbon neutral | Yes |
| `02` | Sustainable | Yes |
| `03` | Sustainable Plus | Yes |

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `isSustainable()` | bool | Returns `true` if code is not `'00'` |
| `isCarbonNeutral()` | bool | Returns `true` if code is `'01'` or `'03'` |

---

## Collection Methods

Both `TimeframeSingleServiceCollection` and `TimeframeMultipleServicesCollection` provide these methods:

### Base Methods

| Method | Return | Description |
|--------|--------|-------------|
| `count()` | int | Number of items in collection |
| `isEmpty()` | bool | Returns `true` if collection is empty |
| `all()` | array | Returns all items as array |
| `first()` | ?SingleTimeFrame | Returns first item or null |
| `last()` | ?SingleTimeFrame | Returns last item or null |
| `get(int $index)` | ?SingleTimeFrame | Returns item at index or null |

### Filtering Methods

All filter methods return a new collection (immutable).

```php
// Filter to only available timeframes
$available = $collection->filterAvailable();

// Filter to unavailable timeframes (useful for debugging)
$unavailable = $collection->filter(function ($timeframe) {
    return !$timeframe->isAvailable();
});

// Filter by specific service (e.g. "daytime" or "evening")
$daytimeOnly = $collection->filter(function ($timeframe) {
    return $timeframe->getService() === 'daytime';
});

$eveningOnly = $collection->filter(function ($timeframe) {
    return $timeframe->getService() === 'evening';
});

// Filter to sustainable delivery options only
$sustainable = $collection->filter(function ($timeframe) {
    return $timeframe->isSustainable();
});

// Filter by specific delivery date
$specificDate = $collection->filter(function ($timeframe) {
    return $timeframe->getDeliveryDate() === '2024-01-17';
});

// Chain multiple filters using successive callbacks
$result = $collection
    ->filter(function ($timeframe) {
        return $timeframe->isAvailable();
    })
    ->filter(function ($timeframe) {
        return $timeframe->getService() === 'daytime';
    })
    ->filter(function ($timeframe) {
        return $timeframe->isSustainable();
    });
```

### Sorting Methods

```php
// Sort by delivery date (ascending)
$sorted = $collection->sortByDate();
```

### Helper Methods

```php
// Get the earliest available delivery date
$earliestDate = $collection->sortByDate()->first()?->deliveryDate; // "2024-01-16" or null

// Custom filtering with callback
$custom = $collection->filter(
    fn (SingleTimeFrame $tf) => $tf->timeFrame?->isMorning()
);

// Find first matching item
$found = $collection->find(
    fn (SingleTimeFrame $tf) => $tf->deliveryDate === '2024-01-17'
);

// Check if any match condition
$hasEvening = $collection->some(
    fn (SingleTimeFrame $tf) => $tf->service === 'evening'
);

// Check if all match condition
$allAvailable = $collection->every(
    fn (SingleTimeFrame $tf) => $tf->isAvailable()
);

// Map to custom array
$dates = $collection->map(
    fn (SingleTimeFrame $tf) => $tf->deliveryDate
);
```

### Iteration

```php
// Iterate using foreach
foreach ($collection as $timeframe) {
    // Process each SingleTimeFrame
}

// Using iterator
$iterator = $collection->getIterator();
```

---

## Error Handling

The SDK throws `ValidationException` for validation errors. For the complete error handling guide, see [Error Handling](../ErrorHandling/README.md).

### Common Validation Errors

| Error Scenario | Description |
|----------------|-------------|
| `deliveryDays` > 14 | Value must be between 1 and 14 |
| `numberOfDays` > 14 | Value must be between 1 and 14 |
| Invalid `handoverDate` | Must be valid ISO 8601 date format |
| Missing `receiverAddress` | Address is required |
| Invalid postal code | Postal code must exist |
| Missing credentials | `customerCode` and `customerNumber` required |
| Invalid `service` | Must be `daytime` or `evening` |
| Invalid `shipmentType` | Must be `parcel`, `letterbox`, `pallet`, or `packet` |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\ValidationException;

try {
    $response = $postnl->checkout()->getSingleServiceTimeframe($request);
    $collection = $response->timeframesSingleServiceCollection();

    // Process timeframes...

} catch (ValidationException $e) {
    // Handle validation errors
    echo "Request failed: " . $e->getMessage();
    echo "Status code: " . $e->getCode();
}
```

---

## Enums Reference

### TimeFrameService

```php
use Postnl\Sdk\Enums\TimeFrameService;

TimeFrameService::Daytime->value;  // 'daytime'
TimeFrameService::Evening->value;  // 'evening'
```

### ShipmentType

```php
use Postnl\Sdk\Enums\ShipmentType;

ShipmentType::Parcel->value;    // 'parcel'
ShipmentType::LetterBox->value; // 'letterbox'
ShipmentType::Pallet->value;    // 'pallet'
ShipmentType::Packet->value;    // 'packet'
```

### LocationSustainabilityCode

```php
use Postnl\Sdk\Enums\LocationSustainabilityCode;

LocationSustainabilityCode::NOT_AVAILABLE->value;    // '00'
LocationSustainabilityCode::CARBON_NEUTRAL->value;   // '01'
LocationSustainabilityCode::SUSTAINABLE->value;      // '02'
LocationSustainabilityCode::SUSTAINABLE_PLUS->value; // '03'

// Helper methods
LocationSustainabilityCode::SUSTAINABLE->getLevel(); // 2
LocationSustainabilityCode::isValid('02');           // true
```

---

## Complete Example

```php
<?php

use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\ShipmentType;
use Postnl\Sdk\Enums\TimeFrameService;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Checkout\V4\Request\MultipleServicesTimeframeRequest;

// Initialize SDK
$postnl = Postnl::sandboxClient('your-api-key');

// Build request
$request = new MultipleServicesTimeframeRequest(
    handoverDate: date('Y-m-d', strtotime('+1 day')),
    receiverAddress: new Address(
        postalCode: '2595AA',
        countryIso: 'NL',
    ),
    services: [
        TimeFrameService::Daytime->value,
        TimeFrameService::Evening->value,
    ],
    shipmentType: ShipmentType::Parcel->value,
    numberOfDays: 7,
    customerCode: 'YOUR_CUSTOMER_CODE',
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
);

try {
    $response = $postnl->checkout()->multipleTimeframes($request);
    $collection = $response->timeframesMultipleServicesCollection();

    // Get earliest sustainable daytime delivery
    $sustainable = $collection
        ->filterAvailable();

    if (!$sustainable->isEmpty()) {
        $earliest = $sustainable->first();
        echo "Earliest sustainable delivery: {$earliest->deliveryDate}\n";
        echo "Time window: {$earliest->timeFrame->getFormattedRange()}\n";
        echo "Sustainability: {$earliest->sustainability->description}\n";
    }

    // List all available evening options
    $eveningOptions = $collection
        ->filterAvailable();

    echo "\nAvailable evening deliveries:\n";
    foreach ($eveningOptions as $option) {
        echo "- {$option->deliveryDate}: {$option->timeFrame->getFormattedRange()}\n";
    }

} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
