# Confirming API Documentation

The Confirming service allows you to confirm shipment pre-announcements with PostNL. This service registers shipments in the PostNL system without generating shipping labels. Use this when you need to pre-announce shipments but will print labels separately, or when using external label printing systems.

For generating shipping labels along with confirmation, see the [Labelling service](../Labelling/README.md).

## Table of Contents

- [Prerequisites](#prerequisites)
- [Confirm Shipment](#confirm-shipment)
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

All Confirming requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

These are automatically injected into the sender object by the SDK.

---

## Confirm Shipment

Confirm a shipment pre-announcement with PostNL.

### Endpoint

```
POST /shipment/delivery/v4/confirm
```

### Request Parameters (ShipmentDeliveryRequest)

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sender` | ShipmentParty | Yes | Sender details including customer credentials and address |
| `receiver` | ShipmentParty | Yes | Receiver contact and address information |
| `labelSettings` | LabelSettings | No | Label format and output configuration |
| `returnOptions` | ReturnOptions | No | Return shipment options |
| `shipmentType` | ShipmentType | No | Type of shipment (default: `parcel`) |
| `handOverDate` | string | No | Date when shipment is handed to PostNL (`YYYY-MM-DD`) |
| `deliveryLocation` | DeliveryLocation | No | Alternative delivery location |
| `services` | Services | No | Additional shipment services |
| `internationalShipmentData` | InternationalShipmentData | No | Data for international shipments |
| `itemCount` | int | No | Number of items in shipment (max 999, default: 1) |
| `items` | ShippingItem[] | No | Individual item details with barcodes |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;

$request = new ShipmentDeliveryRequest(
    sender: ShipmentParty::asSender(
        customerNumber: 'YOUR_CUSTOMER_NUMBER',
        customerCode: 'YOUR_CUSTOMER_CODE',
        address: new Address(
            companyName: 'Your Company',
            street: 'Siriusdreef',
            houseNumber: '42',
            postalCode: '2132WT',
            city: 'Hoofddorp',
            countryIso: Country::NL,
        ),
    ),
    receiver: ShipmentParty::asReceiver(
        address: new Address(
            street: 'Waldorpstraat',
            houseNumber: '3',
            postalCode: '2521CA',
            city: 'Den Haag',
            countryIso: Country::NL,
        ),
        contact: new Contact(
            firstName: 'John',
            lastName: 'Doe',
            email: 'john.doe@example.com',
        ),
    ),
    shipmentType: ShipmentType::Parcel,
    handOverDate: date('Y-m-d', strtotime('+1 day')),
);

$response = $postnl->confirming()->confirmShipmentPreAnnouncement($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = ShipmentDeliveryRequest::fromArray([
    'sender' => [
        'customerNumber' => 'YOUR_CUSTOMER_NUMBER',
        'customerCode'   => 'YOUR_CUSTOMER_CODE',
        'address' => [
            'companyName' => 'Your Company',
            'street'      => 'Siriusdreef',
            'houseNumber' => '42',
            'postalCode'  => '2132WT',
            'city'        => 'Hoofddorp',
            'countryIso'  => 'NL',
        ],
    ],
    'receiver' => [
        'contact' => [
            'firstName' => 'John',
            'lastName'  => 'Doe',
            'email'     => 'john.doe@example.com',
        ],
        'address' => [
            'street'      => 'Waldorpstraat',
            'houseNumber' => '3',
            'postalCode'  => '2521CA',
            'city'        => 'Den Haag',
            'countryIso'  => 'NL',
        ],
    ],
    'type'         => 'parcel',
    'handOverDate' => '2024-01-15',
], $mapper);

$response = $postnl->confirming()->confirmShipmentPreAnnouncement($request);
```

#### Using Fluent Interface

```php
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;

$request = (new ShipmentDeliveryRequest())
    ->sender($sender)
    ->receiver($receiver)
    ->shipmentType(ShipmentType::Parcel)
    ->handOverDate('2024-01-15')
    ->services($services)
    ->itemsCount(1);

$response = $postnl->confirming()->confirmShipmentPreAnnouncement($request);
```

### API Response Structure

```json
{
    "items": [
        {
            "shipmentReference": "REF-2024-001",
            "barcode": "3SDEVC123456789",
            "codingText": "D2132WT+42+0000000",
            "productService": {
                "productData": "3085",
                "services": ["002", "003"]
            }
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->confirming()->confirmShipmentPreAnnouncement($request);

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Get the collection of shipping items
$collection = $response->shippingItems();

// Get total count
echo "Confirmed " . $collection->count() . " shipments\n";

// Iterate over all items
foreach ($collection as $item) {
    echo "Reference: " . $item->shipmentReference . "\n";
    echo "Barcode: " . $item->barcode . "\n";
    echo "Coding Text: " . $item->codingText . "\n";

    // Access product service info
    if ($item->productService !== null) {
        echo "Product: " . $item->productService->productData . "\n";
    }
}

// Get first item directly
$firstItem = $collection->first();

// Get raw array response
$rawData = $response->meta()->toArray();

// Get request correlation ID
$requestId = $response->meta()->requestId;

// On HTTP 429, catch RateLimitException (see docs/ErrorHandling/README.md).
```

---

## Data Models

### ShipmentParty

Represents a party (sender or receiver) in a shipment or return shipment. Use the static constructors to create parties for the correct role:

| Static Method | Use Case | Parameters |
|---------------|----------|-------------|
| `asSender()` | Direct shipment sender (merchant dispatching) | customerNumber, customerCode, address, undeliverableReturnAddress? |
| `asReceiver()` | Direct shipment receiver (consumer receiving) | address, contact?, receiverType? |
| `asReturnSender()` | Return shipment sender (consumer returning) | address, contact? |
| `asReturnReceiver()` | Return shipment receiver (merchant receiving) | customerNumber, customerCode, address, contact? |

```php
final readonly class ShipmentParty
{
    public ?string $customerNumber;              // Customer number (max 10 chars)
    public ?string $customerCode;                // Customer code (max 6 chars)
    public ?Address $address;                    // Postal address
    public ?Address $undeliverableReturnAddress; // Return address for undeliverable items
    public ?Contact $contact;                    // Contact information (name, email, phone)
    public ?ReceiverType $receiverType;          // business or consumer
}
```

### ProcessedShippingItem

Represents a confirmed shipment item in the response.

```php
readonly class ProcessedShippingItem
{
    public ?string $shipmentReference;    // Your reference for the shipment
    public ?string $returnReference;      // Return shipment reference
    public ?Label $label;                 // Generated label (if requested)
    public ?string $barcode;              // PostNL barcode for tracking
    public ?string $returnBarcode;        // Return parcel barcode
    public ?string $partnerId;            // Carrier-id of commercial network partner
    public ?string $partnerBarcode;       // Partner barcode at commercial network partner
    public ?string $codingText;           // Sorting/routing code
    public ?ProductService $productService; // Product and service details
}
```

### Label

Represents label data returned from the API.

```php
readonly class Label
{
    public ?string $content;              // Base64 encoded label content
    public ?string $partnerId;            // Partner identifier
    public ?string $partnerBarcode;       // Partner's barcode reference
    public ?string $mergedLabelContent;   // Merged labels (base64)
    public ?LabelOutputType $outputType;  // Format: pdf, zpl, jpg, gif, png
    public ?LabelType $labelType;         // Type: Label, labelinthebox, shipmentandreturnlabel, retourLabel, CN23, CommercialInvoice
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `isEmpty()` | bool | Returns `true` if label has no content or output type |
| `saveLabelAsFile(string $filepath)` | void | Decode and save label to file |

**Example - Saving a Label:**

```php
$item = $collection->first();

if ($item->label !== null && !$item->label->isEmpty()) {
    $item->label->saveLabelAsFile('/path/to/label.pdf');
}
```

### ProductService

Product and service information for the shipment.

```php
readonly class ProductService
{
    public ?string $productData;  // Product code identifier
    public ?array $services;      // Array of service codes
    public ?array $bundles;       // Bundle information
}
```

### ShippingItem (Request)

Individual item details for multi-item shipments.

```php
final readonly class ShippingItem
{
    public ?string $barcode;                     // Pre-generated barcode
    public ?CustomerReferences $customerReferences; // Custom references
    public ?Dimensions $dimensions;              // Item dimensions
}
```

---

## Collection Methods

`ShippingItemsCollection` provides methods for working with confirmed shipment items:

### Base Methods

| Method | Return | Description |
|--------|--------|-------------|
| `count()` | int | Number of items in collection |
| `isEmpty()` | bool | Returns `true` if collection is empty |
| `all()` | array | Returns all ProcessedShippingItem objects |
| `first()` | ?ProcessedShippingItem | Returns first item or null |
| `last()` | ?ProcessedShippingItem | Returns last item or null |
| `get(int $index)` | ?ProcessedShippingItem | Returns item at index or null |

### Filtering Methods

All filter methods return a new collection (immutable).

```php
// Filter items with barcodes
$withBarcodes = $collection->filter(function ($item) {
    return $item->barcode !== null;
});

// Filter items with labels
$withLabels = $collection->filter(function ($item) {
    return $item->label !== null && !$item->label->isEmpty();
});

// Filter by specific reference prefix
$filtered = $collection->filter(function ($item) {
    return str_starts_with($item->shipmentReference ?? '', 'REF-2024');
});
```

### Helper Methods

```php
// Find first item with a specific barcode
$found = $collection->find(
    fn ($item) => $item->barcode === '3SDEVC123456789'
);

// Check if any items have labels
$hasLabels = $collection->some(
    fn ($item) => $item->label !== null
);

// Check if all items have barcodes
$allHaveBarcodes = $collection->every(
    fn ($item) => $item->barcode !== null
);

// Extract all barcodes as array
$barcodes = $collection->map(
    fn ($item) => $item->barcode
);
```

### Iteration

```php
// Iterate using foreach
foreach ($collection as $item) {
    echo $item->barcode . "\n";
}

// Using iterator
$iterator = $collection->getIterator();
```

---

## Error Handling

The SDK throws semantic exceptions for validation and request errors.

> For the complete exception hierarchy, ProblemDetails, and retry behavior, see the [Error Handling guide](../ErrorHandling/README.md).

### Exception Types

| Exception | Description |
|-----------|-------------|
| `ValidationException` | Invalid request parameters (400, 422) |
| `AuthenticationException` | Invalid or insufficient credentials (401, 403) |
| `RateLimitException` | Too many requests (429, retryable) |
| `TimeoutException` | Request timeout (408, usually retryable) |
| `ClientException` | Other client-side errors (remaining 4xx) |
| `ServerException` | Server error (5xx, retryable) |
| `HttpSdkException` | Base class for all HTTP errors |

### Common Validation Errors

| Error Scenario | Description |
|----------------|-------------|
| Missing sender | Sender information is required |
| Missing receiver | Receiver information is required |
| Invalid postal code | Postal code format is invalid |
| Invalid country | Country code not supported |
| Invalid shipment type | Must be valid ShipmentType value |
| Missing credentials | customerCode and customerNumber required |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\HttpSdkException;

try {
    $response = $postnl->confirming()->confirmShipmentPreAnnouncement($request);
    $collection = $response->shippingItems();

    foreach ($collection as $item) {
        echo "Confirmed: " . $item->barcode . "\n";
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

} catch (HttpSdkException $e) {
    // Handle all other HTTP errors
    echo "Request failed [{$e->statusCode}]: " . $e->getMessage() . "\n";
    if ($e->problemDetails->traceId !== null) {
        echo "Trace ID: " . $e->problemDetails->traceId . "\n";
    }
}
```

---

## Enums Reference

### ShipmentType

```php
use Postnl\Sdk\Enums\Payload\ShipmentType;

ShipmentType::Parcel->value;            // 'parcel'
ShipmentType::NonStandardParcel->value; // 'parcelnonstandard'
ShipmentType::Letter->value;            // 'letter'
ShipmentType::LetterBox->value;         // 'letterbox'
ShipmentType::Pallet->value;            // 'pallet'
ShipmentType::Packet->value;            // 'packet'
```

### ReceiverType

```php
use Postnl\Sdk\Enums\Payload\ReceiverType;

ReceiverType::Business->value;  // 'business'
ReceiverType::Consumer->value;  // 'consumer'
```

### LabelOutputType

```php
use Postnl\Sdk\Enums\Payload\LabelOutputType;

LabelOutputType::PDF->value;  // 'pdf'
LabelOutputType::ZPL->value;  // 'zpl'
LabelOutputType::JPG->value;  // 'jpg'
LabelOutputType::GIF->value;  // 'gif'
LabelOutputType::PNG->value;  // 'png'
```

### LabelType

```php
use Postnl\Sdk\Enums\Payload\LabelType;

LabelType::Label->value;                  // 'Label'
LabelType::LabelInTheBox->value;          // 'labelinthebox'
LabelType::ShipmentAndReturnLabel->value; // 'shipmentandreturnlabel'
```

---

## Complete Example

```php
<?php

use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;

// Initialize SDK
$postnl = Postnl::sandboxClient('your-api-key');

// Build sender (merchant)
$sender = ShipmentParty::asSender(
    customerNumber: 'YOUR_CUSTOMER_NUMBER',
    customerCode: 'YOUR_CUSTOMER_CODE',
    address: new Address(
        companyName: 'My Company BV',
        street: 'Siriusdreef',
        houseNumber: '42',
        postalCode: '2132WT',
        city: 'Hoofddorp',
        countryIso: Country::NL,
    ),
);

// Build receiver (consumer)
$receiver = ShipmentParty::asReceiver(
    address: new Address(
        street: 'Waldorpstraat',
        houseNumber: '3',
        postalCode: '2521CA',
        city: 'Den Haag',
        countryIso: Country::NL,
    ),
    contact: new Contact(
        firstName: 'John',
        lastName: 'Doe',
        email: 'john.doe@example.com',
        mobileNumber: '+31612345678',
    ),
);

// Build request
$request = new ShipmentDeliveryRequest(
    sender: $sender,
    receiver: $receiver,
    shipmentType: ShipmentType::Parcel,
    handOverDate: date('Y-m-d', strtotime('+1 day')),
);

try {
    $response = $postnl->confirming()->preAnnounceShipment($request);
    $collection = $response->shippingItems();

    // Check if successful
    if ($response->isSuccess()) {
        echo "Successfully confirmed {$collection->count()} shipment(s)\n\n";
    }

    // Process confirmed items
    foreach ($collection as $index => $item) {
        echo sprintf("[%d] Shipment Confirmed\n", $index + 1);
        echo "    Reference: " . ($item->shipmentReference ?? 'N/A') . "\n";
        echo "    Barcode: " . ($item->barcode ?? 'N/A') . "\n";
        echo "    Coding Text: " . ($item->codingText ?? 'N/A') . "\n";

        if ($item->productService !== null) {
            echo "    Product: " . $item->productService->productData . "\n";
            echo "    Services: " . implode(', ', $item->productService->services ?? []) . "\n";
        }
        echo "\n";
    }

    // Extract all barcodes for tracking
    $barcodes = $collection->map(fn ($item) => $item->barcode);
    echo "All barcodes: " . implode(', ', array_filter($barcodes)) . "\n";

} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
