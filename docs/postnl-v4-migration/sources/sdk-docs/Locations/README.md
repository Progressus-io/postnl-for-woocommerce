# Locations API Documentation

The Locations functionality allows you to search for PostNL pickup locations where customers can collect their parcels. It supports two search methods: by address (postal code) and by geographic coordinates (latitude/longitude).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Pickup Locations by Address](#pickup-locations-by-address)
- [Pickup Locations by Coordinates](#pickup-locations-by-coordinates)
- [Data Models](#data-models)
- [Collection Methods](#collection-methods)
- [Error Handling](#error-handling)
- [Enums Reference](#enums-reference)
- [Complete Example](#complete-example)

---

## Prerequisites

### SDK Setup

See the main SDK setup guide in the root documentation:
[SDK Root Documentation](../../README.md)

---

### Required Credentials

All Locations requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

---

## Pickup Locations by Address

Search for pickup locations near a postal address.

### Endpoint

```
POST /shipment/delivery/v4/locations/near-address
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `customerNumber` | string | Yes | Your PostNL customer number |
| `customerCode` | string | Yes | Your PostNL customer code |
| `numberOfLocations` | int | No | Number of locations to return (1-10, default: 10) |
| `receiverAddress` | Address | Yes | Address with `postalCode` and `countryIso` |
| `locationType` | string | Yes | Location type: `Retail` or `ParcelLocker` |
| `pickUpDate` | string | Yes | Pickup date (ISO 8601: `YYYY-MM-DD`) |
| `receiverCountryIso` | string | No | Country ISO code override |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\Country;
use Postnl\Sdk\Enums\PickUpLocationType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Locations\V4\Request\PickUpNearAddressRequest;

$request = new PickUpNearAddressRequest(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    numberOfLocations: 5,
    receiverAddress: new Address(
        postalCode: '2521CA',
        countryIso: Country::NL->value,
    ),
    locationType: PickUpLocationType::Retail->value,
    pickUpDate: date('Y-m-d'),
);

$response = $postnl->locations()->getPickupLocationsByAddress($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\Service\Locations\V4\Request\PickUpNearAddressRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = PickUpNearAddressRequest::fromArray([
    'customerNumber'    => 'YOUR_CUSTOMER_NUMBER',
    'customerCode'      => 'YOUR_CUSTOMER_CODE',
    'numberOfLocations' => 5,
    'receiverAddress'   => [
        'postalCode' => '2521CA',
        'countryIso' => 'NL',
    ],
    'locationType'      => 'Retail',
    'pickUpDate'        => '2024-01-15',
], $mapper);

$response = $postnl->locations()->getPickupLocationsByAddress($request);
```

### API Response Structure

```json
{
    "locations": [
        {
            "pickUpLocationId": "176227",
            "locationType": "Retail",
            "name": "Jumbo Den Haag",
            "distance": 523,
            "address": {
                "street": "Weimarstraat",
                "houseNumber": "70",
                "postalCode": "2521CA",
                "city": "Den Haag",
                "countryIso": "NL"
            },
            "coordinates": {
                "latitude": 52.07004808,
                "longitude": 4.32501423
            },
            "openingTimes": {
                "openingTimes": [
                    {
                        "day": "Monday",
                        "times": [
                            { "from": "08:00", "until": "21:00" }
                        ]
                    },
                    {
                        "day": "Tuesday",
                        "times": [
                            { "from": "08:00", "until": "21:00" }
                        ]
                    }
                ]
            },
            "sustainability": {
                "code": "02",
                "description": "Sustainable"
            }
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->locations()->getPickupLocationsByAddress($request);

// Get the collection of locations
$collection = $response->locationsCollection();

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Iterate over all locations
foreach ($collection as $location) {
    echo $location->pickUpLocationId;              // "176227"
    echo $location->locationType;                  // "Retail"
    echo $location->name;                          // "Jumbo Den Haag"
    echo $location->distance;                      // 523 (meters)
    echo $location->getDistanceInKilometers();     // 0.52 (km)
    echo $location->is24Hour() ? '24/7' : 'Limited';
    echo $location->isSustainable();               // true/false
    echo $location->address->getFullAddress();     // Full address string
    echo $location->coordinates->latitude;         // 52.07004808
    echo $location->coordinates->longitude;        // 4.32501423
}

// Get raw array response
$rawData = $response->meta()->toArray();
```

---

## Pickup Locations by Coordinates

Search for pickup locations near geographic coordinates.

### Endpoint

```
POST /shipment/delivery/v4/locations/near-coordinates
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `customerNumber` | string | Yes | Your PostNL customer number |
| `customerCode` | string | Yes | Your PostNL customer code |
| `numberOfLocations` | int | No | Number of locations to return (1-10, default: 10) |
| `coordinates` | Coordinates | Yes | Geographic coordinates with `latitude` and `longitude` |
| `locationType` | string | Yes | Location type: `Retail` or `ParcelLocker` |
| `pickUpDate` | string | Yes | Pickup date (ISO 8601: `YYYY-MM-DD`) |
| `receiverCountryIso` | string | Yes | Country ISO code (e.g., `NL`) |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\Country;
use Postnl\Sdk\Enums\PickUpLocationType;
use Postnl\Sdk\RequestData\V4\Coordinates;
use Postnl\Sdk\Service\Locations\V4\Request\PickUpNearCoordinatesRequest;

$request = new PickUpNearCoordinatesRequest(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    numberOfLocations: 5,
    coordinates: new Coordinates(
        latitude: 52.07004808,
        longitude: 4.32501423,
    ),
    locationType: PickUpLocationType::Retail->value,
    pickUpDate: date('Y-m-d'),
    receiverCountryIso: Country::NL->value,
);

$response = $postnl->locations()->getNearPickupLocationsByCoordinates($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\Service\Locations\V4\Request\PickUpNearCoordinatesRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = PickUpNearCoordinatesRequest::fromArray([
    'customerNumber'     => 'YOUR_CUSTOMER_NUMBER',
    'customerCode'       => 'YOUR_CUSTOMER_CODE',
    'numberOfLocations'  => 5,
    'coordinates'        => [
        'latitude'  => 52.07004808,
        'longitude' => 4.32501423,
    ],
    'locationType'       => 'Retail',
    'pickUpDate'         => '2024-01-15',
    'receiverCountryIso' => 'NL',
], $mapper);

$response = $postnl->locations()->getNearPickupLocationsByCoordinates($request);
```

### API Response Structure

```json
{
    "locations": [
        {
            "pickUpLocationId": "176227",
            "locationType": "Retail",
            "name": "Jumbo Den Haag",
            "distance": 150,
            "address": {
                "street": "Weimarstraat",
                "houseNumber": "70",
                "postalCode": "2521CA",
                "city": "Den Haag",
                "countryIso": "NL"
            },
            "coordinates": {
                "latitude": 52.07004808,
                "longitude": 4.32501423
            },
            "openingTimes": {
                "openingTimes": [
                    {
                        "day": "Monday",
                        "times": [
                            { "from": "00:00", "until": "23:59" }
                        ]
                    }
                ]
            },
            "sustainability": {
                "code": "02",
                "description": "Sustainable"
            }
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->locations()->getNearPickupLocationsByCoordinates($request);

// Get the collection of locations
$collection = $response->locationsCollection();

// Filter and sort locations
$nearestRetail = $collection
    ->filterByLocationType('Retail')
    ->sortByDistance()
    ->first();

if ($nearestRetail) {
    echo "Nearest retail location: " . $nearestRetail->name;
    echo "Distance: " . $nearestRetail->getDistanceInKilometers() . " km";
}

// Get only 24/7 locations
$always247 = $collection->filter24Hour();

// Filter by maximum distance (in meters)
$within1km = $collection->filterByMaxDistance(1000);
```

---

## Data Models

### PickupLocation

Represents a single pickup location.

```php
readonly class PickupLocation
{
    public ?string $pickUpLocationId;          // "176227"
    public ?string $locationType;              // "Retail" or "ParcelLocker"
    public ?string $name;                      // "Jumbo Den Haag"
    public ?int $distance;                     // Distance in meters
    public ?Address $address;                  // Address object
    public ?Coordinates $coordinates;          // Geographic coordinates
    public ?LocationOpeningHours $openingTimes; // Opening hours
    public ?Sustainability $sustainability;    // Sustainability info
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `is24Hour()` | bool | Returns `true` if location is open 24/7 |
| `isSustainable()` | bool | Returns `true` if location has sustainability features |
| `getDistanceInKilometers()` | float | Returns distance in km (rounded to 2 decimals) |

### LocationOpeningHours

Represents the opening hours for a location.

```php
readonly class LocationOpeningHours
{
    public ?array $openingTimes;  // array<DayOpeningTimes>
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `is24Hour()` | bool | Returns `true` if open 24/7 all days |
| `isOpenOn(string $day)` | bool | Returns `true` if open on specific day |
| `getTimesForDay(string $day)` | ?DayOpeningTimes | Returns opening times for specific day |

### DayOpeningTimes

Represents opening times for a specific day.

```php
readonly class DayOpeningTimes
{
    public string $day;    // "Monday", "Tuesday", etc.
    public array $times;   // array<TimeSlot>
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `is24Hour()` | bool | Returns `true` if 24-hour opening |
| `isClosed()` | bool | Returns `true` if location is closed |

### TimeSlot

Represents a time window.

```php
readonly class TimeSlot
{
    public ?string $from;   // "08:00"
    public ?string $until;  // "21:00"
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `getFormattedRange()` | string | Returns `"08:00 - 21:00"` |
| `isMorning()` | bool | Returns `true` if until before 12:00 |
| `isEvening()` | bool | Returns `true` if from 17:00+ |
| `is24Hour()` | bool | Returns `true` if covers full 24-hour period |

### Address

Represents a postal address.

```php
readonly class Address
{
    public ?string $houseNumber;
    public ?string $postalCode;
    public ?string $countryIso;
    public ?string $companyName;
    public ?string $street;
    public ?string $houseNumberAddition;
    public ?string $city;
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `getFullAddress()` | string | Returns single-line address |
| `getFormattedAddress()` | string | Returns multi-line formatted address |

### Coordinates

Represents geographic coordinates.

```php
readonly class Coordinates
{
    public ?float $latitude;   // -90 to 90
    public ?float $longitude;  // -180 to 180
}
```

**Notes:**
- Constructor validates latitude range (-90 to 90) and longitude range (-180 to 180)
- `fromArray()` accepts alternative keys: `lat`/`latitude`, `lng`/`lon`/`longitude`

### Sustainability

Represents sustainability information for a location.

```php
readonly class Sustainability
{
    public ?string $code;         // "00", "01", "02", "03"
    public ?string $description;  // "Not available", "Sustainable", etc.
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

`PickUpLocationsCollection` provides these methods:

### Base Methods

| Method | Return | Description |
|--------|--------|-------------|
| `count()` | int | Number of items in collection |
| `isEmpty()` | bool | Returns `true` if collection is empty |
| `all()` | array | Returns all items as array |
| `first()` | ?PickupLocation | Returns first item or null |
| `last()` | ?PickupLocation | Returns last item or null |
| `get(int $index)` | ?PickupLocation | Returns item at index or null |

### Filtering Methods

All filter methods return a new collection (immutable).

```php
// Filter by location type
$retailOnly = $collection->filterByLocationType('Retail');
$lockersOnly = $collection->filterByLocationType('ParcelLocker');

// Filter by sustainability code
$sustainable = $collection->filterBySustainability('02');

// Filter by maximum distance (in meters)
$within500m = $collection->filterByMaxDistance(500);
$within1km = $collection->filterByMaxDistance(1000);

// Filter to 24/7 locations only
$always247 = $collection->filter24Hour();

// Custom filtering with callback
$custom = $collection->filter(
    fn (PickupLocation $loc) => $loc->distance < 1000 && $loc->isSustainable()
);

// Chain multiple filters
$result = $collection
    ->filterByLocationType('Retail')
    ->filter24Hour()
    ->filterByMaxDistance(2000);
```

### Sorting Methods

```php
// Sort by distance (ascending - nearest first)
$sorted = $collection->sortByDistance();
```

### Helper Methods

```php
// Get the nearest location
$nearest = $collection->sortByDistance()->first();

// Find first matching item
$found = $collection->find(
    fn (PickupLocation $loc) => $loc->name === 'Jumbo Den Haag'
);

// Check if any match condition
$has24Hour = $collection->some(
    fn (PickupLocation $loc) => $loc->is24Hour()
);

// Check if all match condition
$allSustainable = $collection->every(
    fn (PickupLocation $loc) => $loc->isSustainable()
);

// Map to custom array
$names = $collection->map(
    fn (PickupLocation $loc) => $loc->name
);
```

### Iteration

```php
// Iterate using foreach
foreach ($collection as $location) {
    // Process each PickupLocation
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
| `numberOfLocations` > 10 | Value must be between 1 and 10 |
| Invalid `locationType` | Must be `Retail` or `ParcelLocker` |
| Invalid `pickUpDate` | Must be valid ISO 8601 date format (YYYY-MM-DD) |
| Missing `receiverAddress` | Address is required for address search |
| Missing `coordinates` | Coordinates are required for coordinates search |
| Invalid postal code | Postal code must be valid format |
| Missing credentials | `customerCode` and `customerNumber` required |
| Invalid latitude | Must be between -90 and 90 |
| Invalid longitude | Must be between -180 and 180 |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\ValidationException;

try {
    $response = $postnl->locations()->getPickupLocationsByAddress($request);
    $collection = $response->locationsCollection();

    // Process locations...

} catch (ValidationException $e) {
    // Handle validation errors
    echo "Request failed: " . $e->getMessage();
    echo "Status code: " . $e->getCode();
}
```

---

## Enums Reference

### PickUpLocationType

```php
use Postnl\Sdk\Enums\PickUpLocationType;

PickUpLocationType::Retail->value;       // 'Retail'
PickUpLocationType::ParcelLocker->value; // 'ParcelLocker'
```

### Country

```php
use Postnl\Sdk\Enums\Country;

Country::NL->value;  // 'NL'
Country::BE->value;  // 'BE'
Country::DE->value;  // 'DE'
// ... other country codes
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
use Postnl\Sdk\Enums\Country;
use Postnl\Sdk\Enums\PickUpLocationType;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\Service\Locations\V4\Request\PickUpNearAddressRequest;

// Initialize SDK
$postnl = Postnl::sandboxClient('your-api-key');

// Build request
$request = new PickUpNearAddressRequest(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    numberOfLocations: 10,
    receiverAddress: new Address(
        postalCode: '2521CA',
        countryIso: Country::NL->value,
    ),
    locationType: PickUpLocationType::Retail->value,
    pickUpDate: date('Y-m-d'),
);

try {
    $response = $postnl->locations()->getPickupLocationsByAddress($request);
    $collection = $response->locationsCollection();

    // Get nearest 24/7 sustainable location
    $filtered = $collection
        ->filter24Hour()
        ->filterBySustainability('02')
        ->sortByDistance();

    if (!$filtered->isEmpty()) {
        $nearest = $filtered->first();
        echo "Best pickup location: {$nearest->name}\n";
        echo "Distance: {$nearest->getDistanceInKilometers()} km\n";
        echo "Sustainability: {$nearest->sustainability->description}\n";
    }

    // List all retail locations within 1km
    $nearby = $collection
        ->filterByLocationType('Retail')
        ->filterByMaxDistance(1000)
        ->sortByDistance();

    echo "\nNearby retail locations (within 1km):\n";
    foreach ($nearby as $location) {
        $hours = $location->is24Hour() ? '24/7' : 'Limited hours';
        echo "- {$location->name}: {$location->getDistanceInKilometers()} km ({$hours})\n";
    }

} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
