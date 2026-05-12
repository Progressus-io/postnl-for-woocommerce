# ShipmentDelivery API Documentation

The ShipmentDelivery service combines label generation AND shipment confirmation in a single API call. This is the recommended approach when you need both a shipping label and want to pre-announce the shipment to PostNL simultaneously.

This service differs from the individual services:
- **[Confirming service](../Confirming/README.md)** - Pre-announces shipments WITHOUT generating labels
- **[Labelling service](../Labelling/README.md)** - Generates labels only, without confirmation

## Table of Contents

- [Prerequisites](#prerequisites)
- [Label Confirm](#label-confirm)
- [Label Settings](#label-settings)
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

All ShipmentDelivery requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

These are automatically injected into the sender object by the SDK.

---

## Label Confirm

Generate a shipping label and confirm the shipment in a single API call.

### Endpoint

```
POST /shipment/delivery/v4/labelconfirm
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
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\LabelSettings;
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
    labelSettings: new LabelSettings(
        outputType: LabelOutputType::PDF,
    ),
    shipmentType: ShipmentType::Parcel,
    handOverDate: date('Y-m-d', strtotime('+1 day')),
);

$response = $postnl->shipmentDelivery()->labelConfirm($request);
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
    'labelSettings' => [
        'outputType' => 'pdf',
    ],
    'type'         => 'parcel',
    'handOverDate' => '2024-01-15',
], $mapper);

$response = $postnl->shipmentDelivery()->labelConfirm($request);
```

#### Using Fluent Interface

```php
use Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest;

$request = (new ShipmentDeliveryRequest())
    ->sender($sender)
    ->receiver($receiver)
    ->labelSettings($labelSettings)
    ->shipmentType(ShipmentType::Parcel)
    ->handOverDate('2024-01-15')
    ->services($services)
    ->itemsCount(1);

$response = $postnl->shipmentDelivery()->labelConfirm($request);
```

### API Response Structure

```json
{
    "items": [
        {
            "shipmentReference": "REF-2024-001",
            "barcode": "3SDEVC123456789",
            "codingText": "D2132WT+42+0000000",
            "labels": [
                {
                    "label": "JVBERi0xLjQK... (base64 encoded)",
                    "outputType": "pdf",
                    "labelType": "Label"
                }
            ],
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
$response = $postnl->shipmentDelivery()->labelConfirm($request);

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Get the collection of shipping items
$collection = $response->shippingItems();

// Get total count
echo "Processed " . $collection->count() . " shipment(s)\n";

// Iterate over all items
foreach ($collection as $item) {
    echo "Reference: " . $item->shipmentReference . "\n";
    echo "Barcode: " . $item->barcode . "\n";
    echo "Coding Text: " . $item->codingText . "\n";

    // Save the label to a file
    $label = $item->labels?->first();
    if ($label !== null && !$label->isEmpty()) {
        $label->saveLabelAsFile('/path/to/labels/' . $item->barcode . '.pdf');
        echo "Label saved!\n";
    }

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

## Label Settings

Configure label generation options using the `LabelSettings` class.

### LabelSettings Properties

| Property | Type | Description |
|----------|------|-------------|
| `outputType` | LabelOutputType | The file format of the label (pdf, zpl, gif, jpg, png) |
| `resolution` | LabelResolution | The resolution in DPI (200, 300, 600) |
| `pageOrientation` | LabelPageOrientation | The orientation of the page (portrait, landscape) |
| `mergeType` | LabelMergeType | Merge multiple labels into one document (singlepdf, pdfa6toa4) |
| `positioning` | LabelPositioning | Position of the label on the page (topleft, topright, bottomleft, bottomright) |
| `printMethod` | LabelPrintMethod | Specifies which party will print the label (consumerPrint, retailPrint) |

### LabelSettings Example

```php
use Postnl\Sdk\Enums\Payload\LabelMergeType;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\LabelPageOrientation;
use Postnl\Sdk\Enums\Payload\LabelPositioning;
use Postnl\Sdk\Enums\Payload\LabelPrintMethod;
use Postnl\Sdk\Enums\Payload\LabelResolution;
use Postnl\Sdk\RequestData\V4\LabelSettings;

// PDF label for consumer printing
$labelSettings = new LabelSettings(
    outputType: LabelOutputType::PDF,
    resolution: LabelResolution::DPI_300,
    pageOrientation: LabelPageOrientation::Portrait,
    printMethod: LabelPrintMethod::ConsumerPrint,
);

// ZPL label for thermal printers
$thermalLabelSettings = new LabelSettings(
    outputType: LabelOutputType::ZPL,
    resolution: LabelResolution::DPI_200,
);

// Merged labels (A6 to A4)
$mergedLabelSettings = new LabelSettings(
    outputType: LabelOutputType::PDF,
    mergeType: LabelMergeType::PDFA6TOA4,
    positioning: LabelPositioning::TopLeft,
);
```

### Print Method Recommendations

| Print Method | Recommended Output Type | Use Case |
|--------------|------------------------|----------|
| `consumerPrint` | PDF | End consumers printing at home |
| `retailPrint` | PNG or JPG | Retail/store printing with image-based printers |

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

### DeliveryLocation

Represents an alternative delivery location for the shipment.

```php
final readonly class DeliveryLocation
{
    public ?string $pickupLocationId;  // Pickup location id (parcel locker / PostNL location)
    public ?Address $address;          // Or alternative delivery address (exactly one of the two)
}
```

### ProcessedShippingItem

Represents a processed shipment item with its generated label.

```php
readonly class ProcessedShippingItem
{
    public ?string $shipmentReference;        // Your reference for the shipment
    public ?string $returnReference;          // Return shipment reference (e.g. label-in-the-box)
    public ?LabelsCollection $labels;         // Collection of generated labels
    public ?string $barcode;                  // PostNL barcode for tracking
    public ?string $returnBarcode;            // Return parcel barcode
    public ?string $partnerId;                // Carrier-id of commercial network partner (last mile)
    public ?string $partnerBarcode;           // Partner barcode at commercial network partner
    public ?string $codingText;               // Sorting/routing code (e.g. letterbox NL)
    public ?ProductService $productService;   // Product and service details
}
```

### Label

Represents label data returned from the API.

```php
readonly class Label
{
    public ?string $label;                // Base64 encoded label content
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

$label = $item->labels?->first();

if ($label !== null && !$label->isEmpty()) {
    // Save as PDF
    $label->saveLabelAsFile('/path/to/label.pdf');

    // Or save with barcode as filename
    $filename = $item->barcode . '.' . $label->outputType->value;
    $label->saveLabelAsFile('/path/to/labels/' . $filename);
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

`ShippingItemsCollection` provides methods for working with processed shipment items:

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
// Filter items with labels
$withLabels = $collection->filter(function ($item) {
    return $item->labels !== null && !$item->labels->isEmpty();
});

// Filter items with barcodes
$withBarcodes = $collection->filter(function ($item) {
    return $item->barcode !== null;
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
    fn ($item) => $item->labels !== null
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

### Saving All Labels

```php
$collection = $response->shippingItems();
$outputDir = '/path/to/labels/';

foreach ($collection as $item) {
    $label = $item->labels?->first();
    if ($label !== null && !$label->isEmpty()) {
        $filename = $item->barcode . '.' . $label->outputType->value;
        $label->saveLabelAsFile($outputDir . $filename);
    }
}
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
| `TimeoutException` | Request timed out (408, retryable) |
| `ClientException` | Other client errors (4xx, non-retryable) |
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
| Invalid label output type | Must be valid LabelOutputType value |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\AuthenticationException;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\Exception\HttpSdkException;

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
    $collection = $response->shippingItems();

    foreach ($collection as $item) {
        $label = $item->labels?->first();
        if ($label !== null && !$label->isEmpty()) {
            $label->saveLabelAsFile('/path/to/label.pdf');
        }
        echo "Processed: " . $item->barcode . "\n";
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

### LabelResolution

```php
use Postnl\Sdk\Enums\Payload\LabelResolution;

LabelResolution::DPI_200->value;  // 200
LabelResolution::DPI_300->value;  // 300
LabelResolution::DPI_600->value;  // 600
```

### LabelPageOrientation

```php
use Postnl\Sdk\Enums\Payload\LabelPageOrientation;

LabelPageOrientation::Portrait->value;   // 'portrait'
LabelPageOrientation::Landscape->value;  // 'landscape'
```

### LabelMergeType

```php
use Postnl\Sdk\Enums\Payload\LabelMergeType;

LabelMergeType::SinglePDF->value;   // 'singlepdf'
LabelMergeType::PDFA6TOA4->value;   // 'pdfa6toa4'
```

### LabelPositioning

```php
use Postnl\Sdk\Enums\Payload\LabelPositioning;

LabelPositioning::TopLeft->value;      // 'topleft'
LabelPositioning::TopRight->value;     // 'topright'
LabelPositioning::BottomLeft->value;   // 'bottomleft'
LabelPositioning::BottomRight->value;  // 'bottomright'
```

### LabelPrintMethod

```php
use Postnl\Sdk\Enums\Payload\LabelPrintMethod;

LabelPrintMethod::ConsumerPrint->value;  // 'consumerPrint'
LabelPrintMethod::RetailPrint->value;    // 'retailPrint'
```

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

---

## Complete Example

```php
<?php

use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\Payload\Country;
use Postnl\Sdk\Enums\Payload\LabelOutputType;
use Postnl\Sdk\Enums\Payload\LabelResolution;
use Postnl\Sdk\Enums\Payload\ShipmentType;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\LabelSettings;
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

// Configure label settings
$labelSettings = new LabelSettings(
    outputType: LabelOutputType::PDF,
    resolution: LabelResolution::DPI_300,
);

// Build request
$request = new ShipmentDeliveryRequest(
    sender: $sender,
    receiver: $receiver,
    labelSettings: $labelSettings,
    shipmentType: ShipmentType::Parcel,
    handOverDate: date('Y-m-d', strtotime('+1 day')),
);

try {
    $response = $postnl->shipmentDelivery()->labelConfirm($request);
    $collection = $response->shippingItems();

    // Check if successful
    if ($response->isSuccess()) {
        echo "Successfully processed {$collection->count()} shipment(s)\n\n";
    }

    // Process items
    foreach ($collection as $index => $item) {
        echo sprintf("[%d] Shipment Processed\n", $index + 1);
        echo "    Reference: " . ($item->shipmentReference ?? 'N/A') . "\n";
        echo "    Barcode: " . ($item->barcode ?? 'N/A') . "\n";
        echo "    Coding Text: " . ($item->codingText ?? 'N/A') . "\n";

        // Save the label
        $label = $item->labels?->first();
        if ($label !== null && !$label->isEmpty()) {
            $filename = '/path/to/labels/' . $item->barcode . '.pdf';
            $label->saveLabelAsFile($filename);
            echo "    Label saved to: " . $filename . "\n";
        }

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
