# Return Shipment API Documentation

The Return Shipment functionality allows you to generate return labels for customers to send parcels back. It creates shipment labels with return-specific options, supporting both domestic and international returns.

## Table of Contents

- [Prerequisites](#prerequisites)
- [Generate Return Shipment](#generate-return-shipment)
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

All Return Shipment requests require:
- `customerCode` - Your PostNL customer code
- `customerNumber` - Your PostNL customer number

These credentials are automatically merged into the `receiver` object via the `CredentialStrategy::INTO_RECEIVER` strategy.

---

## Generate Return Shipment

Generate a return shipment label for customers to return parcels.

### Endpoint

```
POST /shipment/delivery/v4/return/generate
```

### Request Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `sender` | ShipmentParty | Yes | Sender information (customer returning the parcel) |
| `receiver` | ShipmentParty | Yes | Receiver information (your company details) |
| `labelSettings` | LabelSettings | No | Label output configuration (format, resolution, etc.) |
| `returnOptions` | ReturnOptions | No | Return-specific options (return period, valuable return, etc.) |
| `items` | array\<ShippingItem\> | No | Items to be returned with their barcodes and references |

### Code Examples

#### Using Constructor

```php
use Postnl\Sdk\Enums\Country;
use Postnl\Sdk\Enums\LabelOutputType;
use Postnl\Sdk\Enums\LabelPageOrientation;
use Postnl\Sdk\Enums\LabelResolution;
use Postnl\Sdk\Enums\LabelPrintMethod;
use Postnl\Sdk\Enums\ReturnPeriod;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\LabelSettings;
use Postnl\Sdk\RequestData\V4\ReturnOptions\DomesticReturnOptions;
use Postnl\Sdk\RequestData\V4\ReturnOptions\ReturnOptions;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest;
use Postnl\Sdk\ResponseData\V4\ShippingItem;

$request = new ReturnShipmentRequest(
    sender: ShipmentParty::asReturnSender(
        address: new Address(
            street: 'Klantstraat',
            houseNumber: '123',
            postalCode: '1234AB',
            city: 'Amsterdam',
            countryIso: Country::NL->value,
        ),
        contact: new Contact(
            firstName: 'Jan',
            lastName: 'Klant',
            email: 'jan.klant@example.com',
        ),
    ),
    receiver: ShipmentParty::asReturnReceiver(
        customerNumber: 'YOUR_CUSTOMER_NUMBER',
        customerCode: 'YOUR_CUSTOMER_CODE',
        address: new Address(
            street: 'Waldorpstraat',
            houseNumber: '3',
            postalCode: '2521CA',
            city: 'Den Haag',
            countryIso: Country::NL->value,
        ),
    ),
    labelSettings: new LabelSettings(
        outputType: LabelOutputType::PDF->value,
        resolution: LabelResolution::DPI_300->value,
        pageOrientation: LabelPageOrientation::Portrait->value,
        printMethod: LabelPrintMethod::Consumer->value,
    ),
    returnOptions: new ReturnOptions(
        domesticReturnOptions: new DomesticReturnOptions(
            returnPeriod: ReturnPeriod::IN_35_DAYS,
            valuableReturn: true,
        ),
    ),
);

$response = $postnl->returnShipment()->generateReturn($request);
```

#### Using fromArray Factory

```php
use Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = ReturnShipmentRequest::fromArray([
    'sender' => [
        'address' => [
            'street'      => 'Klantstraat',
            'houseNumber' => '123',
            'postalCode'  => '1234AB',
            'city'        => 'Amsterdam',
            'countryIso'  => 'NL',
        ],
        'contact' => [
            'firstName' => 'Jan',
            'lastName'  => 'Klant',
            'email'     => 'jan.klant@example.com',
        ],
    ],
    'receiver' => [
        'customerNumber' => 'YOUR_CUSTOMER_NUMBER',
        'customerCode'   => 'YOUR_CUSTOMER_CODE',
        'address' => [
            'street'      => 'Waldorpstraat',
            'houseNumber' => '3',
            'postalCode'  => '2521CA',
            'city'        => 'Den Haag',
            'countryIso'  => 'NL',
        ],
    ],
    'labelSettings' => [
        'outputType'      => 'pdf',
        'resolution'      => 300,
        'pageOrientation' => 'portrait',
        'printMethod'     => 'consumerPrint',
    ],
    'returnOptions' => [
        'domestic' => [
            'returnPeriod'   => 35,
            'valuableReturn' => true,
        ],
    ],
], $mapper);

$response = $postnl->returnShipment()->generateReturn($request);
```

#### Using Fluent Interface

```php
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\LabelSettings;
use Postnl\Sdk\RequestData\V4\ReturnOptions\ReturnOptions;
use Postnl\Sdk\ResponseData\V4\ShippingItem;
use Postnl\Sdk\Support\PayloadMapper;

$mapper  = PayloadMapper::create();
$request = (new ReturnShipmentRequest())
    ->sender(ShipmentParty::asReturnSender(
        address: new Address(
            street: 'Klantstraat',
            houseNumber: '123',
            postalCode: '1234AB',
            city: 'Amsterdam',
            countryIso: 'NL',
        ),
        contact: new Contact(
            lastName: 'Klant',
            email: 'klant@example.com',
        ),
    ))
    ->receiver(ShipmentParty::asReturnReceiver(
        customerNumber: 'YOUR_CUSTOMER_NUMBER',
        customerCode: 'YOUR_CUSTOMER_CODE',
        address: new Address(
            street: 'Waldorpstraat',
            houseNumber: '3',
            postalCode: '2521CA',
            city: 'Den Haag',
            countryIso: 'NL',
        ),
    ))
    ->labelSettings(LabelSettings::fromArray([
        'outputType' => 'pdf',
        'resolution' => 300,
    ], $mapper))
    ->returnOptions(ReturnOptions::fromArray([
        'domestic' => [
            'returnPeriod' => 35,
        ],
    ], $mapper))
    ->addItem(ShippingItem::fromArray([
        'barcode'            => '3SDEVC123456789',
        'customerReferences' => [
            'shipmentReference' => 'ORDER-12345',
            'costCenter'        => 'RETURNS',
        ],
    ], $mapper));

$response = $postnl->returnShipment()->generateReturn($request);
```

### API Response Structure

```json
{
    "items": [
        {
            "shipmentReference": "ORDER-12345",
            "labels": [
                {
                    "label": "JVBERi0xLjMKJeLjz9...",
                    "outputType": "PDF",
                    "labelType": "Return Label"
                }
            ],
            "barcode": "3SDEVC330399651",
            "productService": {
                "productData": "Returns Homedress",
                "services": [
                    "Online Label on request"
                ]
            }
        }
    ]
}
```

### Working with the Response

```php
$response = $postnl->returnShipment()->generateReturn($request);

// Get the collection of processed shipping items
$collection = $response->shippingItemsCollection();

// Check response status
if ($response->isSuccess()) {
    echo "Status: " . $response->meta()->statusCode; // 200
}

// Iterate over all processed items
foreach ($collection as $item) {
    echo $item->shipmentReference;              // "ORDER-12345"
    echo $item->barcode;                        // "3SDEVC330399651"
    echo $item->codingText;                     // Routing code (if present)
    $label = $item->labels?->first();
    echo $label->outputType->value;             // "pdf"
    echo $label->labelType->value;              // "labelinthebox"
    echo $item->productService->productData;    // "Returns Homedress"

    // Save the label to a file
    if ($label !== null && !$label->isEmpty()) {
        $label->saveLabelAsFile('/path/to/return-label.pdf');
    }
}

// Get the first item
$firstItem = $collection->first();

// Get raw array response
$rawData = $response->meta()->toArray();
```

---

## Data Models

### ReturnShipmentRequest

The main request object for generating return shipments.

```php
class ReturnShipmentRequest
{
    public function __construct(
        private ?ShipmentParty $sender = null,
        private ?ShipmentParty $receiver = null,
        private ?LabelSettings $labelSettings = null,
        private ?ReturnOptions $returnOptions = null,
        array $items = [],
    );
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `sender(ShipmentParty $sender)` | static | Set the sender (fluent) |
| `receiver(ShipmentParty $receiver)` | static | Set the receiver (fluent) |
| `labelSettings(LabelSettings $labelSettings)` | static | Set label settings (fluent) |
| `returnOptions(ReturnOptions $returnOptions)` | static | Set return options (fluent) |
| `addItem(ShippingItem $item)` | static | Add a shipping item (fluent) |
| `withItems(array $items)` | static | Replace all items (fluent) |
| `clearItems()` | static | Remove all items (fluent) |
| `toArray(PayloadMapperInterface $mapper)` | array | Convert to array |
| `fromArray(array $data, PayloadMapperInterface $mapper)` | self | Create from array (static) |

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

### Address

Represents a postal address.

```php
readonly class Address
{
    public ?string $houseNumber;
    public ?string $postalCode;
    public ?string $countryIso;
    public ?string $companyName;
    public ?string $departmentName;
    public ?string $street;
    public ?string $houseNumberAddition;
    public ?string $city;
    public ?string $addressLine;
    public ?InternationalAddressData $internationalAddressData;
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `getFullAddress()` | string | Returns single-line address |
| `getFormattedAddress()` | string | Returns multi-line formatted address |

### Contact

Represents contact information for the sender.

```php
readonly class Contact
{
    public ?string $companyName;
    public ?string $lastName;
    public ?string $mobileNumber;
    public ?string $smsNumber;
    public ?string $firstName;
    public ?string $email;
    public ?string $phoneNumber;
    public ?string $language;
}
```

### LabelSettings

Configures the output format and appearance of the label.

```php
readonly class LabelSettings
{
    public ?string $outputType;       // "pdf", "jpg", "png", "gif"
    public ?int $resolution;          // 200, 300, or 600 DPI
    public ?string $pageOrientation;  // "portrait" or "landscape"
    public ?string $mergeType;        // Merge multiple labels
    public ?string $positioning;      // Label positioning on page
    public ?string $printMethod;      // "retailPrint" or "consumerPrint"
}
```

### ReturnOptions

Configures return-specific options.

```php
readonly class ReturnOptions
{
    public ?string $labelType;                           // Label type
    public ?string $returnBarcode;                       // Pre-assigned barcode
    public ?Address $returnAddress;                      // Alternative return address
    public ?DomesticReturnOptions $domesticReturnOptions; // Domestic return settings
    public ?bool $returnBlock;                           // Block return option
}
```

### DomesticReturnOptions

Configures domestic return settings.

```php
readonly class DomesticReturnOptions
{
    public ?ReturnPeriod $returnPeriod;  // 20 or 35 days
    public ?bool $valuableReturn;         // Valuable item handling
}
```

### ProcessedShippingItem

Represents a processed return shipment in the response.

```php
readonly class ProcessedShippingItem
{
    public ?string $shipmentReference;        // Your reference
    public ?LabelsCollection $labels;         // Collection of generated labels
    public ?string $barcode;                  // Assigned barcode
    public ?string $codingText;               // Routing code
    public ?ProductService $productService;   // Product/service info
}
```

### Label

Represents a generated shipping label.

```php
readonly class Label
{
    public ?string $label;                // Base64 encoded label data
    public ?string $partnerId;            // Partner ID
    public ?string $partnerBarcode;       // Partner barcode
    public ?string $mergedLabelContent;   // Merged label data
    public ?LabelOutputType $outputType;  // PDF, ZPL, JPG, etc.
    public ?LabelType $labelType;         // Label type
}
```

**Methods:**

| Method | Return | Description |
|--------|--------|-------------|
| `isEmpty()` | bool | Returns `true` if label has no content |
| `saveLabelAsFile(string $filepath)` | void | Save label to file |

---

## Collection Methods

`ShippingItemsCollection` provides these methods:

### Base Methods

| Method | Return | Description |
|--------|--------|-------------|
| `count()` | int | Number of items in collection |
| `isEmpty()` | bool | Returns `true` if collection is empty |
| `all()` | array | Returns all items as array |
| `first()` | ?ProcessedShippingItem | Returns first item or null |
| `last()` | ?ProcessedShippingItem | Returns last item or null |
| `get(int $index)` | ?ProcessedShippingItem | Returns item at index or null |

### Filtering Methods

All filter methods return a new collection (immutable).

```php
// Custom filtering with callback
$pdfLabels = $collection->filter(
    fn (ProcessedShippingItem $item) => $item->labels?->first()?->outputType === LabelOutputType::PDF
);

// Filter to items with barcodes
$withBarcodes = $collection->filter(
    fn (ProcessedShippingItem $item) => $item->barcode !== null
);
```

### Helper Methods

```php
// Find first matching item
$found = $collection->find(
    fn (ProcessedShippingItem $item) => $item->barcode === '3SDEVC330399651'
);

// Check if any match condition
$hasLabels = $collection->some(
    fn (ProcessedShippingItem $item) => $item->labels !== null && !$item->labels->isEmpty()
);

// Check if all match condition
$allHaveBarcodes = $collection->every(
    fn (ProcessedShippingItem $item) => $item->barcode !== null
);

// Map to custom array
$barcodes = $collection->map(
    fn (ProcessedShippingItem $item) => $item->barcode
);
```

### Iteration

```php
// Iterate using foreach
foreach ($collection as $item) {
    // Process each ProcessedShippingItem
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
| Missing `sender` | Sender information is required |
| Missing `receiver` | Receiver information is required |
| Invalid `customerNumber` | Customer number must be valid |
| Invalid `customerCode` | Customer code must be valid |
| Invalid `outputType` | Must be `pdf`, `jpg`, `png`, or `gif` |
| Invalid `printMethod` | Must be `retailPrint` or `consumerPrint` |
| Invalid `returnPeriod` | Must be 20 or 35 |
| Missing address fields | Required address fields must be provided |

### Example Error Handling

```php
use Postnl\Sdk\Exception\Client\ValidationException;

try {
    $response = $postnl->returnShipment()->generateReturn($request);
    $collection = $response->shippingItemsCollection();

    // Process items...

} catch (ValidationException $e) {
    // Handle validation errors
    echo "Request failed: " . $e->getMessage();
    echo "Status code: " . $e->getCode();
}
```

---

## Enums Reference

### LabelOutputType

```php
use Postnl\Sdk\Enums\LabelOutputType;

LabelOutputType::PDF->value;  // 'pdf'
LabelOutputType::JPG->value;  // 'jpg'
LabelOutputType::PNG->value;  // 'png'
LabelOutputType::GIF->value;  // 'gif'
// Note: ZPL is not applicable to return endpoints
```

### LabelOrientation

```php
use Postnl\Sdk\Enums\LabelPageOrientation;

LabelPageOrientation::Portrait->value;   // 'portrait'
LabelPageOrientation::Landscape->value;  // 'landscape'
```

### LabelResolution

```php
use Postnl\Sdk\Enums\LabelResolution;

LabelResolution::DPI_200->value;  // 200
LabelResolution::DPI_300->value;  // 300
LabelResolution::DPI_600->value;  // 600
```

### PrintMethod

```php
use Postnl\Sdk\Enums\LabelPrintMethod;

LabelPrintMethod::Retail->value;    // 'retailPrint' (NL only, use PNG/JPG)
LabelPrintMethod::Consumer->value;  // 'consumerPrint' (BE only, use PDF)
```

### ReturnPeriod

```php
use Postnl\Sdk\Enums\ReturnPeriod;

ReturnPeriod::IN_20_DAYS->value;  // 20
ReturnPeriod::IN_35_DAYS->value;  // 35
```

### Country

```php
use Postnl\Sdk\Enums\Country;

Country::NL->value;  // 'NL'
Country::BE->value;  // 'BE'
Country::DE->value;  // 'DE'
// ... other country codes
```

---

## Complete Example

```php
<?php

use Postnl\Sdk\Client\Postnl;
use Postnl\Sdk\Enums\Country;
use Postnl\Sdk\Enums\LabelOutputType;
use Postnl\Sdk\Enums\LabelResolution;
use Postnl\Sdk\Enums\LabelPrintMethod;
use Postnl\Sdk\Enums\ReturnPeriod;
use Postnl\Sdk\Exception\Client\ValidationException;
use Postnl\Sdk\RequestData\V4\Address;
use Postnl\Sdk\RequestData\V4\Contact;
use Postnl\Sdk\RequestData\V4\LabelSettings;
use Postnl\Sdk\RequestData\V4\ReturnOptions\DomesticReturnOptions;
use Postnl\Sdk\RequestData\V4\ReturnOptions\ReturnOptions;
use Postnl\Sdk\RequestData\V4\ShipmentParty;
use Postnl\Sdk\RequestData\V4\ReturnShipment\ReturnShipmentRequest;

// Initialize SDK
$postnl = Postnl::sandboxClient('your-api-key');

// Build request for a customer return
$request = new ReturnShipmentRequest(
    sender: ShipmentParty::asReturnSender(
        address: new Address(
            street: 'Klantstraat',
            houseNumber: '42',
            postalCode: '1234AB',
            city: 'Amsterdam',
            countryIso: Country::NL->value,
        ),
        contact: new Contact(
            firstName: 'Jan',
            lastName: 'Klant',
            email: 'jan.klant@example.com',
            mobileNumber: '+31612345678',
        ),
    ),
    receiver: ShipmentParty::asReturnReceiver(
        customerNumber: 'YOUR_CUSTOMER_NUMBER',
        customerCode: 'YOUR_CUSTOMER_CODE',
        address: new Address(
            companyName: 'Your Company B.V.',
            street: 'Waldorpstraat',
            houseNumber: '3',
            postalCode: '2521CA',
            city: 'Den Haag',
            countryIso: Country::NL->value,
        ),
    ),
    labelSettings: new LabelSettings(
        outputType: LabelOutputType::PDF->value,
        resolution: LabelResolution::DPI_300->value,
        printMethod: LabelPrintMethod::Consumer->value,
    ),
    returnOptions: new ReturnOptions(
        domesticReturnOptions: new DomesticReturnOptions(
            returnPeriod: ReturnPeriod::IN_35_DAYS,
            valuableReturn: false,
        ),
    ),
);

try {
    $response = $postnl->returnShipment()->generateReturn($request);
    $collection = $response->shippingItems();

    if ($collection->isEmpty()) {
        echo "No return labels generated.\n";
        return;
    }

    // Process each generated return label
    foreach ($collection as $index => $item) {
        echo "Return #{$index}:\n";
        echo "  Barcode: {$item->barcode}\n";
        echo "  Reference: {$item->shipmentReference}\n";

        if ($item->productService !== null) {
            echo "  Product: {$item->productService->productData}\n";
        }

        // Save the label
        $label = $item->labels?->first();
        if ($label !== null && !$label->isEmpty()) {
            $filename = "return-label-{$item->barcode}.pdf";
            $label->saveLabelAsFile("/tmp/{$filename}");
            echo "  Label saved: {$filename}\n";
        }

        echo "\n";
    }

    // Get all barcodes for tracking
    $barcodes = $collection->map(fn($item) => $item->barcode);
    echo "All return barcodes: " . implode(', ', array_filter($barcodes)) . "\n";

} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```
