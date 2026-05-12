# PostNL V4 SDK/API Developer Reference

## 1. Reference Scope

- SDK package: `postnl/api-client-sdk`, local source at `postnl-sdk-audit/vendor/postnl/api-client-sdk`.
- SDK reference/version: `composer.json` branch alias `dev-main: 1.x-dev`; no installed lock version found in inspected SDK folder.
- Postman collection: `postnl-docs/PostNL Future Proof V4 API's.postman_collection.json`.
- Plugin inspected: `postnl-for-woocommerce-org`.
- Purpose: implementation and review reference for PostNL V4 SDK integration in the WooCommerce plugin.
- Evidence paths are local repository paths from the inspected folders only.

## 2. SDK Summary

| Item | Value | Evidence |
|---|---|---|
| package name | `postnl/api-client-sdk` | `postnl-sdk-audit/vendor/postnl/api-client-sdk/composer.json` |
| version/reference | `dev-main: 1.x-dev`; no concrete Composer installed version in SDK source folder | `postnl-sdk-audit/vendor/postnl/api-client-sdk/composer.json` |
| PHP requirement | `>=8.2 <8.5`; requires `ext-mbstring` | `postnl-sdk-audit/vendor/postnl/api-client-sdk/composer.json` |
| namespace | `Postnl\Sdk\` | `composer.json` PSR-4 autoload |
| autoload path | `vendor/autoload.php` when installed by Composer; package maps `Postnl\Sdk\` to `src/` | `composer.json` |
| main facade/client classes | `Postnl\Sdk\Client\Postnl`, `Postnl\Sdk\Client\Client`, `Postnl\Sdk\Client\PostnlClientInterface` | `src/Client/Postnl.php`, `src/Client/Client.php`, `src/Client/PostnlClientInterface.php` |
| builder/factory classes | `Postnl\Sdk\Client\ClientBuilder`, `Postnl\Sdk\Service\ServiceFactory`, `Postnl\Sdk\Transport\TransportFactory` | `src/Client/ClientBuilder.php`, `src/Service/ServiceFactory.php`, `src/Transport/TransportFactory.php` |
| auth methods | API key via `Auth::apiKey()` / `apikey` header; OAuth client credentials via `Auth::oauthClientCredentials()` / `Authorization: Bearer ...`; `Auth::fromEnv()` | `src/Auth/Auth.php`, `src/Auth/ApiKeyRequestAuthenticator.php`, `src/Auth/Oauth/OauthRequestAuthenticator.php` |
| sandbox/production handling | `ClientBuilder::SANDBOX_BASE_URI = https://api-sandbox.postnl.nl/`; production base URI `https://api.postnl.nl/`; `withSandbox(bool)` | `src/Client/ClientBuilder.php` |
| transport layer | PSR-18 client, PSR-17 factories, Guzzle helper builder, retry/log/trace/plugin transport decorators | `src/Client/ClientBuilder.php`, `src/Transport/*` |
| exception system | `PostnlSdkException`, `HttpSdkException`, client/server/transport/auth exceptions, parsers/normalizers | `src/Exception/*`, `docs/ErrorHandling/README.md` |
| docs folder | Service docs under `docs/Barcode`, `docs/ShipmentDelivery`, `docs/Labelling`, `docs/Confirming`, `docs/ReturnShipment`, `docs/Locations`, `docs/TimeFrame`, `docs/Extension` | `postnl-sdk-audit/vendor/postnl/api-client-sdk/docs` |
| versioning | `Version::V1` is deprecated with `#[DeprecatedVersion]`; `Version::V4` is the default (no explicit `withApiVersion()` call required); `SDK_POSTNL_API_VERSION` accepts `1`, `4`, or `5` but no V5 service implementations appear in SDK docs | `docs/Versioning.md`, `docs/Configuration/README.md` |
| package distribution | Distributed via Private Packagist at `https://repo.packagist.com/postnl/`; customers add the repo + an `auth.json` with a read-only token; internal runbook is in `docs/Distribution/README.md` (not customer-facing) | `docs/Distribution/README.md` |

## 3. API Area Coverage Matrix

| API area | Postman endpoint(s) | SDK service | SDK method | Request class | Response class | Existing plugin flow | Status |
|---|---|---|---|---|---|---|---|
| Shipment Delivery V4 | `POST /shipment/delivery/v4/labelconfirm` | `ShipmentDeliveryInterface` | `labelConfirm()` | `Postnl\Sdk\RequestData\V4\ShipmentDelivery\ShipmentDeliveryRequest` | `LabelConfirmResponse` | `src/Rest_API/Shipping/Client.php` old `POST /v1/shipment?confirm=true` | Covered |
| Return Shipment V4 | `POST /shipment/delivery/v4/return/generate` | `ReturnShipmentInterface` | `generateReturn()` | `ReturnShipmentRequest` | `GenerateReturnResponse` | `src/Rest_API/Return_Label/Client.php`, `src/Rest_API/Shipping/Client.php` | Covered |
| Labelling V4 | Not present | `LabellingInterface` | `requestLabel()` | `ShipmentDeliveryRequest` | `LabellingResponse` | Shipping label flow currently old client | Covered |
| Confirming / pre-announce V4 | Not present | `ConfirmingInterface` | `preAnnounceShipment()` | `ShipmentDeliveryRequest` | `ConfirmingResponse` | No distinct old pre-announce-only flow found | Covered |
| Barcode | Not present | `BarcodeInterface` | `generateBarcode()` | `BarcodeRequest` | `GenerateBarcodeResponse` | `src/Rest_API/Barcode/Client.php` old `GET /shipment/v1_1/barcode` | Covered |
| Locations / pickup locations | Not present | `Client::locations()` → `NearAddressPickupLocationsInterface`, `NearCoordinatesPickupLocationsInterface` | `getPickupLocationsByAddress()`, `getNearPickupLocationsByCoordinates()` | `PickUpNearAddressRequest`, `PickUpNearCoordinatesRequest` | `PickUpLocationsResponse` | Checkout pickup points through `src/Rest_API/Checkout/Client.php` old `POST /shipment/v1/checkout` | Covered |
| TimeFrame / delivery dates | Not present | `Client::checkout()` → `SingleServiceTimeframeInterface`, `MultipleServicesTimeframeInterface` | `getSingleServiceTimeframe()`, `getMultipleServicesTimeframe()` | `SingleServiceTimeframeRequest`, `MultipleServicesTimeframeRequest` | `TimeFrameSingleServiceResponse`, `TimeframesMultipleServicesResponse` | Checkout delivery days through `src/Rest_API/Checkout/Client.php` | Covered |
| Checkout coverage | Not present | No standalone `checkout()` service on `Client` | N/A | N/A | N/A | `src/Rest_API/Checkout/Client.php` old `POST /shipment/v1/checkout` | Needs mapping |
| Postal code check / address validation | Not present | Extension only: `PostalCodeCheckExtension` | `ConfigurableAction::execute()` | `PostalCodeCheckRequest` | `PostalCodeAddressResponse` | `src/Rest_API/Postcode_Check/Client.php` old `POST /shipment/checkout/v1/postalcodecheck` | Partially covered |
| Smart Returns | `POST /shipment/delivery/v4/return/generate` examples named Smart Returns | `ReturnShipmentInterface` | `generateReturn()` | `ReturnShipmentRequest` | `GenerateReturnResponse` | `src/Rest_API/Smart_Returns/Client.php` old `POST /shipment/v2_2/label/` | Needs mapping |
| Shipment & Return activation / activatereturn | `POST /shipment/delivery/v4/return/activate` | Not found | Not found | Not found | Not found | `src/Rest_API/Shipment_and_Return/Client.php` old `POST /parcels/v1/shipment/activatereturn` | Needs mapping |
| Fill In With PostNL OAuth | Not present | SDK OAuth client credentials only | `Postnl::oauthClient()`, `Auth::oauthClientCredentials()` | N/A | N/A | `src/Frontend/Fill_In_With_Postnl.php`, `src/Frontend/Fill_In_With_Postnl_Handler.php` browser PKCE flow | Keep old client |

## 4. Endpoint Reference

Endpoint:
`POST /shipment/delivery/v4/labelconfirm`

| Item | Value |
|---|---|
| API area | Shipment Delivery V4 |
| endpoint names | 50 Postman examples under `PostNL Shipment API` |
| auth/header | `apikey: {{APIkey-Sandbox}}`; SDK adds `Content-Type: application/json` by default |
| common request sections | `receiver`, `sender`, `itemCount`, `items[]`, `labelSettings`, `shipmentType`; optional `services`, `returnOptions`, `deliveryLocation`, `handOverDate`, `internationalShipmentData` |
| matching SDK service/method | `Client::shipmentDelivery()->labelConfirm(ShipmentDeliveryRequest)` |
| matching plugin flow | `src/Rest_API/Shipping/Client.php` old `POST /v1/shipment?confirm=true`; `src/Rest_API/Letterbox/Client.php` extends shipping client |

Variations found:
- Parcel NL; Parcel NL LiB; Parcel NL With Dimensions; Parcel NL Multicollo.
- Insured; ReturnWhenNotHome; StatedAddressOnly; Signature on Delivery; deliveryCode on Delivery.
- ADR Low Quantity; AgeCheck 16+; AgeCheck 18+.
- Guaranteed Before `10:00`, `12:00`, `17:00`; Evening Delivery.
- Pickup at PostNL Location; APL; Pickup + Insured; Pickup + Insured + AgeCheck 18+.
- Letterbox Parcel NL; Letterbox Parcel NL 48hours.
- Parcel BE to NL; NL to BE; BE to BE.
- EU parcel/packet/letterbox Track and Trace, Insured, Insured Plus, Untracked.
- ROW parcel/letterbox Track and Trace, Insured, Insured Plus, Untracked.

Endpoint:
`POST /shipment/delivery/v4/return/generate`

| Item | Value |
|---|---|
| API area | Return Shipment V4 / Smart Returns examples |
| endpoint names | `NL-NL Single Label`, `NL-BE Single Label`, `BE-NL Single Label`, `BE-BE Single Label`, `NL-NL Smart Returns`, `NL-BE Smart Returns`, `NL-NL Antwoordnummer`, `BE-NL Antwoordnummer`, `NL-NL Single Label Valuable Return` |
| auth/header | `Content-Type: application/json`, `Accept: application/json`, `apikey: {{APIkey-Sandbox}}` |
| common request sections | `receiver`, `sender`, `labelSettings`, `returnOptions`, `shipmentType`, `items[]` |
| unique variations/options | NL/BE sender receiver combinations; Smart Returns names; Antwoordnummer return address; `returnOptions.domestic.valuableReturn` |
| matching SDK service/method | `Client::returnShipment()->generateReturn(ReturnShipmentRequest)` |
| matching plugin flow | `src/Rest_API/Return_Label/Client.php`; `src/Rest_API/Smart_Returns/Client.php` needs mapping from old label V2.2 body |

Endpoint:
`POST /shipment/delivery/v4/return/activate`

| Item | Value |
|---|---|
| API area | Shipment & Return activation / activatereturn |
| endpoint names | `ActivateReturn denied no label`, `ActivateReturn warning Label` |
| auth/header | `Content-Type: application/json`, `apikey: {{APIkey-Sandbox}}` |
| common request sections | `barcode`, `sender.customerNumber`, `source`, `label` |
| unique variations/options | Postman examples use identical visible body shape; names indicate denied/warning responses |
| matching SDK service/method | Not found |
| matching plugin flow | `src/Rest_API/Shipment_and_Return/Client.php` old `POST /parcels/v1/shipment/activatereturn` |

SDK endpoints not present in Postman collection:

| API area | Method | Path | SDK service/method | Request |
|---|---|---|---|---|
| Barcode | `POST` | `/shipment/delivery/v4/barcode` | `Client::barcode()->generateBarcode()` | `BarcodeRequest` |
| Labelling | `POST` | `/shipment/delivery/v4/label` | `Client::labelling()->requestLabel()` | `ShipmentDeliveryRequest` |
| Confirming | `POST` | `/shipment/delivery/v4/confirm` | `Client::confirming()->preAnnounceShipment()` | `ShipmentDeliveryRequest` |
| Locations by address | `POST` | `/shipment/delivery/v4/locations/near-address` | `Client::locations()->getPickupLocationsByAddress()` | `PickUpNearAddressRequest` |
| Locations by coordinates | `POST` | `/shipment/delivery/v4/locations/near-coordinates` | `Client::locations()->getNearPickupLocationsByCoordinates()` | `PickUpNearCoordinatesRequest` |
| Single TimeFrame | `POST` | `/shipment/delivery/v4/timeframe/singleservice` | `Client::checkout()->getSingleServiceTimeframe()` | `SingleServiceTimeframeRequest` |
| Multiple TimeFrames | `POST` | `/shipment/delivery/v4/timeframe/multipleservices` | `Client::checkout()->getMultipleServicesTimeframe()` | `MultipleServicesTimeframeRequest` |
| Postal code check extension | `GET` | `/shipment/checkout/v1/postalcodecheck` | `PostalCodeCheckExtension` via `extensions()` | `PostalCodeCheckRequest` |

## 5. Payload Field Reference

### receiver

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `receiver.customerNumber` | string | Return V4 receiver | `1122334455` | Merchant receiver for returns; SDK `ShipmentParty::$customerNumber` |
| `receiver.customerCode` | string | Return V4 receiver | `DEVC` | SDK `ShipmentParty::$customerCode` |
| `receiver.type` | enum/string | Shipment V4 receiver | `consumer` | SDK field is `receiverType` mapped to API key `type` |
| `receiver.contact.firstName` | string | Shipment/Return | `Test` | SDK `Contact::$firstName` |
| `receiver.contact.lastName` | string | Shipment/Return | `Persoon` | SDK `Contact::$lastName` |
| `receiver.contact.email` | string | Shipment/Return | `test.persoon@postnl.nl` | SDK `Contact::$email` |
| `receiver.contact.language` | enum/string | Shipment/Return | `NL` | SDK `Language` enum |
| `receiver.contact.mobileNumber` | string | Shipment/Return | `0612345678` | Max length noted in SDK docblock: 16 |
| `receiver.address.countryIso` | enum/string | Shipment/Return | `NL` | SDK `Country` enum; ISO2 |
| `receiver.address.city` | string | Shipment/Return | `Den Haag` | SDK `Address::$city` |
| `receiver.address.companyName` | string | Shipment/Return | `TestBedrijf` | SDK `Address::$companyName` |
| `receiver.address.departmentName` | string | ROW shipment | `Afdeling` | International examples |
| `receiver.address.houseNumber` | string | Shipment/Return | `3` | SDK `Address::$houseNumber` |
| `receiver.address.houseNumberAddition` | string/null | Shipment/Return | `bis` | SDK `Address::$houseNumberAddition` |
| `receiver.address.postalCode` | string | Shipment/Return | `2521CA` | SDK `Address::$postalCode` |
| `receiver.address.street` | string | Shipment/Return | `Waldorpstraat` | SDK `Address::$street` |
| `receiver.address.addressLine` | string/null | Shipment | Unclear | Present in SDK; Postman examples mostly null/omitted |
| `receiver.address.internationalAddressData.*` | object | ROW shipment | `area`, `region`, `buildingName`, `floor`, `doorcode` | SDK `InternationalAddressData` |

### sender

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `sender.customerNumber` | string | Shipment sender / activation sender | `11223344` | SDK credential strategy can merge into sender |
| `sender.customerCode` | string | Shipment sender | `DEVC` | SDK credential strategy can merge into sender |
| `sender.contact.firstName` | string | Return sender | `Test` | Consumer return sender |
| `sender.contact.lastName` | string | Return sender | `Verzender` | Consumer return sender |
| `sender.contact.email` | string | Return sender | `verzendemail@postnl.nl` | SDK `Contact` |
| `sender.contact.language` | enum/string | Return sender | `NL` | SDK `Language` enum |
| `sender.contact.mobileNumber` | string | Return sender | `+31687654321` | SDK `Contact` |
| `sender.address.countryIso` | enum/string | Shipment/Return | `NL` | SDK `Country` enum |
| `sender.address.city` | string | Shipment/Return | `Den Haag` | SDK `Address` |
| `sender.address.companyName` | string | Shipment/Return | `TestBedrijf` | SDK `Address` |
| `sender.address.houseNumber` | string | Shipment/Return | `2` | SDK `Address` |
| `sender.address.houseNumberAddition` | string/null | Shipment/Return | `bis` | SDK `Address` |
| `sender.address.postalCode` | string | Shipment/Return | `2521CA` | SDK `Address` |
| `sender.address.street` | string | Shipment/Return | `Teststraat` | SDK `Address` |
| `sender.undeliverableReturnAddress` | object | SDK model | Unclear | Present in SDK; not visible in extracted Postman field list |

### items[]

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `itemCount` | int | Shipment V4 | `1` | SDK derives from item collection when `items[]` present |
| `items[].barcode` | string | Shipment/Return | `{{barcode}}` | SDK `ShippingItem::$barcode` |
| `items[].customerReferences.shipmentReference` | string | Shipment | `Reference` | SDK `CustomerReferences` |
| `items[].customerReferences.costCenter` | string | Shipment | `Factuurnummer` | SDK `CustomerReferences` |
| `items[].customerReferences.returnReference` | string | Return | `returnReference` | SDK `CustomerReferences` |
| `items[].dimensions` | object | Shipment/Return | See `dimensions` | SDK `Dimensions` |

### dimensions

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `items[].dimensions.length` | int | Shipment with dimensions | `1000` | SDK constructor property `lengthCm`, API key `length` |
| `items[].dimensions.width` | int | Shipment with dimensions | `1000` | SDK constructor property `widthCm`, API key `width` |
| `items[].dimensions.height` | int | Shipment with dimensions | `1000` | SDK constructor property `heightCm`, API key `height` |
| `items[].dimensions.weight` | int | Shipment/Return | `1000` | SDK constructor property `weightGr`, API key `weight` |
| `items[].dimensions.volume` | int | Shipment with dimensions | `1000` | SDK constructor property `volumeCm3`, API key `volume` |

### customerReferences

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `customerReferences.shipmentReference` | string | Shipment item | `Reference` | Old plugin maps order number to old `Reference` |
| `customerReferences.costCenter` | string | Shipment item | `Factuurnummer` | Optional reference |
| `customerReferences.returnReference` | string | Return item | `returnReference` | Return reference/RMA candidate |

### labelSettings

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `labelSettings.outputType` | enum/string | Shipment/Return | `PDF` in Postman, SDK enum values are lowercase `pdf`, `zpl`, `jpg`, `gif`, `png` | Docs/code casing must be verified against API |
| `labelSettings.resolution` | enum/int | Shipment/Return | `200` | SDK enum values `200`, `300`, `600` |
| `labelSettings.pageOrientation` | enum/string | Shipment/Return | `portrait` | SDK enum `portrait`, `landscape` |
| `labelSettings.printMethod` | enum/string | Labelling, ShipmentDelivery, Return | `consumerPrint` | SDK enum `consumerPrint` (BE, PDF recommended), `retailPrint` (NL, PNG/JPG recommended); present in all label endpoints, not return-only |
| `labelSettings.mergeType` | enum/string | SDK model | Unclear | SDK enum `singlepdf`, `pdfa6toa4` |
| `labelSettings.positioning` | enum/string | SDK model | Unclear | SDK enum `topleft`, `topright`, `bottomleft`, `bottomright` |

### services

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `services.insuredValue` | float | Insured shipment | `250` | Replaces old insurance amount/product option when mapped |
| `services.returnWhenNotHome` | bool | ReturnWhenNotHome | `true` | SDK `Services::$returnWhenNotHome` |
| `services.statedAddressOnly` | bool | StatedAddressOnly | `true` | SDK `Services::$statedAddressOnly` |
| `services.deliveryConfirmation` | enum/string | Signature/delivery code | `signature` | SDK enum `signature`, `deliverycode` |
| `services.minimalAgeCheck` | enum/string | AgeCheck | `16+`, `18+` | SDK enum values `16+`, `18+` |
| `services.adrLq` | bool | ADR Low Quantity | `true` | SDK field/API key is `adrLq`; one extracted Postman field appeared as `adrlq`, verify casing before implementation |
| `services.registered` | bool | SDK model | Unclear | Present in SDK; not found in extracted Postman examples |
| `services.deliveryWindow.service` | enum/string | Evening | `evening` | SDK enum also contains `daytime` |
| `services.deliveryWindow.guaranteedBefore` | enum/string | Guaranteed delivery | `10:00`, `12:00`, `17:00` | SDK comments note `12:00` validation issue `AITS-382` |
| `services.deliveryWindow.duration` | enum/string | Letterbox 48hours | `non24hours` | SDK enum also `24hours` |

### returnOptions

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `returnOptions.labelType` | enum/string | LiB / return labels | `labelinthebox` | SDK enum also `Label`, `shipmentandreturnlabel`, `retourLabel`, `CN23`, `CommercialInvoice` |
| `returnOptions.returnBarcode` | string | LiB | `{{returnBarcode}}` | SDK `ReturnOptions::$returnBarcode` |
| `returnOptions.returnAddress` | object | LiB / EU return | `returnOptions.returnAddress.*` | SDK `Address` |
| `returnOptions.domestic.returnPeriod` | enum/int | Return generate | `20` | SDK `ReturnPeriod` enum confirms `IN_20_DAYS` (20) and `IN_35_DAYS` (35) only; values `100`, `200`, `365` not found in provided SDK docs |
| `returnOptions.domestic.valuableReturn` | bool | Valuable return | `false` | SDK `DomesticReturnOptions::$valuableReturn` |
| `returnOptions.returnBlock` | bool | SDK model | Unclear | Present in SDK; likely relates activation, but not found in extracted Postman return/generate examples |

### TimeFrame request fields

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `handoverDate` | string/date | Single/multiple TimeFrame | Unclear | SDK `SingleServiceTimeframeRequest`, `MultipleServicesTimeframeRequest` |
| `deliveryDays` | int | Single TimeFrame | Unclear | Single service only |
| `numberOfDays` | int | Multiple TimeFrames | Unclear | Multiple services only |
| `receiverAddress` | object | TimeFrame | Unclear | SDK `Address` |
| `service` | enum/string | Single TimeFrame | `daytime` / `evening` | SDK `DeliveryWindowService` |
| `services[]` | enum/string[] | Multiple TimeFrames | `daytime`, `evening` | SDK validates enum items |
| `shipmentType` | enum/string | TimeFrame | `parcel` | SDK `ShipmentType` |
| `customerCode` | string | TimeFrame | Unclear | SDK field |
| `customerNumber` | string | TimeFrame | Unclear | SDK field |

### Locations request fields

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `numberOfLocations` | int | Locations | Unclear | SDK docblock min 1, max 10 |
| `receiverAddress` | object | Near-address locations | Unclear | SDK `Address` |
| `coordinates.latitude` | float/string | Near-coordinates locations | Unclear | SDK `Coordinates` |
| `coordinates.longitude` | float/string | Near-coordinates locations | Unclear | SDK `Coordinates` |
| `locationType` | enum/string | Locations | `Retail`, `ParcelLocker` | SDK `PickUpLocationType` |
| `pickUpDate` | string/date | Locations | Unclear | Delivery date at pickup location |
| `receiverCountryIso` | enum/string | Near-coordinates locations | Unclear | SDK `Country` |
| `customerCode` | string | Locations | Unclear | SDK field |
| `customerNumber` | string | Locations | Unclear | SDK field |

### Barcode request fields

| Field | Type if inferable | Used in | Example value | Notes |
|---|---|---|---|---|
| `customerNumber` | string | Barcode V4 | Unclear | SDK `BarcodeRequest` |
| `customerCode` | string | Barcode V4 | Unclear | SDK `BarcodeRequest` |
| `serieStart` | string | Barcode V4 | Unclear | SDK constructor parameter is `serieStart`/`serieEnd`; `fromArray()` key is `seriesStart`/`seriesEnd` — inconsistency in SDK docs; verify against installed SDK version |
| `serieEnd` | string | Barcode V4 | Unclear | SDK field; see note on `serieStart` |
| `numberOfBarcodes` | int | Barcode V4 | Unclear | SDK field |

## 6. Services / Options Reference

| Option | Field path | Example value | Example request names | Notes |
|---|---|---|---|---|
| insuredValue | `services.insuredValue` | `250` | Parcel NL Insured; EU/ROW Insured | Maps old insured product option/amount after product mapping |
| returnWhenNotHome | `services.returnWhenNotHome` | `true` | Return When Not Home combinations | Replaces old `return_no_answer` when mapped |
| statedAddressOnly | `services.statedAddressOnly` | `true` | Stated Address Only combinations | Replaces old `only_home_address` when mapped |
| deliveryConfirmation | `services.deliveryConfirmation` | `signature`, `deliverycode` | Signature on Delivery; deliveryCode on Delivery | Replaces old signature/delivery code options |
| minimalAgeCheck | `services.minimalAgeCheck` | `16+`, `18+` | AgeCheck 16+; AgeCheck 18+ | Replaces old adult/id check option when mapped |
| adrlq | `services.adrLq` | `true` | ADR Low Quantity | SDK casing `adrLq`; extracted Postman casing looked `adrlq`; verify request casing |
| deliveryWindow.guaranteedBefore | `services.deliveryWindow.guaranteedBefore` | `10:00`, `12:00`, `17:00` | Guaranteed Before 10/12/17 | SDK comment flags `12:00` validation issue |
| deliveryWindow.service | `services.deliveryWindow.service` | `evening` | Evening Delivery | TimeFrame services use `daytime`/`evening` |
| deliveryWindow.duration | `services.deliveryWindow.duration` | `non24hours` | Letterbox Parcel NL 48hours | SDK enum also `24hours` |
| labelType | `returnOptions.labelType` | `labelinthebox` | Parcel NL LiB | SDK docs confirm 3 `LabelType` values: `Label`, `labelinthebox`, `shipmentandreturnlabel`; values `retourLabel`, `CN23`, `CommercialInvoice` not found in provided SDK docs |
| returnBarcode | `returnOptions.returnBarcode` | `{{returnBarcode}}` | Parcel NL LiB | Used with label-in-the-box |
| returnAddress | `returnOptions.returnAddress.*` | `Teststraat`, `Den Haag` | Parcel NL LiB; EU return options | SDK `Address` |
| shipmentType | `shipmentType` | `parcel`, `letterbox`, `packet` | Parcel, Letterbox, Packet examples | SDK enum also `parcelnonstandard`, `letter`, `pallet` |
| dimensions | `items[].dimensions.*` | `length: 1000`, `weight: 1000` | With Dimensions; EU/ROW examples | Old client used `Dimension.Weight`; V4 item dimensions object |
| customerReferences | `items[].customerReferences.*` | `Reference`, `Factuurnummer` | Shipment/Return examples | Map order number/cost center/RMA references here |

## 7. SDK Service Reference

| Area | service/facade method | interface method | request class | response class | docs path | matching endpoint from Postman | short purpose |
|---|---|---|---|---|---|---|---|
| ShipmentDelivery | `Client::shipmentDelivery()` | `ShipmentDeliveryInterface::labelConfirm()` | `ShipmentDeliveryRequest` | `LabelConfirmResponse` | `docs/ShipmentDelivery/README.md` | `POST /shipment/delivery/v4/labelconfirm` | Create shipment and label/confirm response |
| ReturnShipment | `Client::returnShipment()` | `ReturnShipmentInterface::generateReturn()` | `ReturnShipmentRequest` | `GenerateReturnResponse` | `docs/ReturnShipment/README.md` | `POST /shipment/delivery/v4/return/generate` | Generate return shipment/label |
| Labelling | `Client::labelling()` | `LabellingInterface::requestLabel()` | `ShipmentDeliveryRequest` | `LabellingResponse` | `docs/Labelling/README.md` | Not present | Request label endpoint without confirm path |
| Confirming | `Client::confirming()` | `confirmShipmentPreAnnouncement()` / `preAnnounceShipment()` (SDK docs inconsistent; prior code inspection found `preAnnounceShipment()` on interface — use that) | `ShipmentDeliveryRequest` | `ConfirmingResponse` | `docs/Confirming/README.md` | Not present | Pre-announce/confirm shipment without label |
| Barcode | `Client::barcode()` | `BarcodeInterface::generateBarcode()` | `BarcodeRequest` | `GenerateBarcodeResponse` | `docs/Barcode/README.md` | Not present | Generate one or more barcodes |
| Locations by address | `Client::locations()` | `getPickupLocationsByAddress()` | `PickUpNearAddressRequest` | `PickUpLocationsResponse` | `docs/Locations/README.md` | Not present | Pickup locations near postal address |
| Locations by coordinates | `Client::locations()` | `getNearPickupLocationsByCoordinates()` | `PickUpNearCoordinatesRequest` | `PickUpLocationsResponse` | `docs/Locations/README.md` | Not present | Pickup locations near lat/long |
| Single TimeFrame | `Client::checkout()` | `getSingleServiceTimeframe()` | `SingleServiceTimeframeRequest` | `TimeFrameSingleServiceResponse` | `docs/TimeFrame/README.md` | Not present | Delivery timeframes for one service |
| Multiple TimeFrames | `Client::checkout()` | `getMultipleServicesTimeframe()` (also `multipleTimeframes()` in SDK complete example — SDK docs are inconsistent) | `MultipleServicesTimeframeRequest` | `TimeframesMultipleServicesResponse` | `docs/TimeFrame/README.md` | Not present | Delivery timeframes for multiple services |
| PostalCodeCheck extension | `Client::extensions()` | `ConfigurableAction::execute()` | `PostalCodeCheckRequest` | `PostalCodeAddressResponse` | `docs/Extension/README.md` | Not present | V1 checkout postal-code/address lookup extension |
| Auth | `Postnl::client()`, `Postnl::sandboxClient()`, `Postnl::oauthClient()`, `Postnl::sandboxOauthClient()`, `Auth::*` | N/A | N/A | N/A | `docs/Configuration/README.md` | API key headers in Postman | Request authentication |
| Extensions | `Client::extensions()` | `ClientExtensionsInterface::register()`, `getAs()` | Extension-defined | Extension-defined | `docs/Extension/README.md` | Not present | Add unsupported endpoints such as postal-code check or activatereturn |

## 8. Current Plugin Flow Mapping

| Plugin flow | Current file/class | Old endpoint | V4 endpoint | SDK method | Recommended path | Notes |
|---|---|---|---|---|---|---|
| Barcode | `src/Rest_API/Barcode/Client.php` | `GET /shipment/v1_1/barcode` | `/shipment/delivery/v4/barcode` | `barcode()->generateBarcode()` | SDK after mapping | Old query fields `Type`, `Serie`, `Range` do not directly match SDK `serieStart`, `serieEnd`, `numberOfBarcodes` |
| Shipping labels | `src/Rest_API/Shipping/Client.php` | `POST /v1/shipment?confirm=true` | `/shipment/delivery/v4/labelconfirm` | `shipmentDelivery()->labelConfirm()` | SDK after mapping | Must map old `ProductCodeDelivery`/`ProductOptions` to V4 `shipmentType` and `services` |
| Return labels | `src/Rest_API/Return_Label/Client.php` | `POST /v1/shipment?confirm=true` via shipping client | `/shipment/delivery/v4/return/generate` or `/labelconfirm` with `returnOptions` | `returnShipment()->generateReturn()` or `shipmentDelivery()->labelConfirm()` | SDK after mapping | Preserve existing label output and return address behavior |
| Letterbox labels | `src/Rest_API/Letterbox/Client.php` | `POST /v1/shipment?confirm=true` | `/shipment/delivery/v4/labelconfirm` | `shipmentDelivery()->labelConfirm()` | SDK after mapping | Map to `shipmentType=letterbox` and applicable dimensions/services |
| Checkout delivery options | `src/Rest_API/Checkout/Client.php`, `src/Frontend/Container.php`, `src/Frontend/Delivery_Day.php` | `POST /shipment/v1/checkout` | `/shipment/delivery/v4/timeframe/singleservice`, `/shipment/delivery/v4/timeframe/multipleservices` | `checkout()->getSingleServiceTimeframe()`, `checkout()->getMultipleServicesTimeframe()` | Hybrid | Checkout V1 combines delivery dates and locations; V4 split requires aggregation |
| Pickup points | `src/Rest_API/Checkout/Client.php`, `src/Frontend/Dropoff_Points.php` | `POST /shipment/v1/checkout` | `/shipment/delivery/v4/locations/near-address`, `/shipment/delivery/v4/locations/near-coordinates` | `locations()->getPickupLocationsByAddress()`, `locations()->getNearPickupLocationsByCoordinates()` | SDK after mapping | Preserve UI response shape expected by checkout frontend |
| TimeFrame / delivery dates | `src/Rest_API/Checkout/Client.php`, `src/Frontend/Container.php` | `POST /shipment/v1/checkout` | `/shipment/delivery/v4/timeframe/*` | `checkout()->getSingleServiceTimeframe()`, `checkout()->getMultipleServicesTimeframe()` | SDK after mapping | DeliveryDate is represented by TimeFrame V4, not standalone DeliveryDate service |
| Postcode check | `src/Rest_API/Postcode_Check/Client.php` | `POST /shipment/checkout/v1/postalcodecheck` | `/shipment/checkout/v1/postalcodecheck` | `PostalCodeCheckExtension` | Old client | SDK extension uses `GET` while plugin uses `POST`; validation remains outside V4 |
| Smart Returns | `src/Rest_API/Smart_Returns/Client.php` | `POST /shipment/v2_2/label/` | `/shipment/delivery/v4/return/generate` possibly | `returnShipment()->generateReturn()` | Needs confirmation | Postman names Smart Returns under return/generate, but old body/product behavior requires mapping confirmation |
| Shipment & Return activation | `src/Rest_API/Shipment_and_Return/Client.php` | `POST /parcels/v1/shipment/activatereturn` | `/shipment/delivery/v4/return/activate` | Not found | Needs confirmation | Postman has V4 endpoint; SDK service absent |
| Fill In With PostNL OAuth | `src/Frontend/Fill_In_With_Postnl.php`, `src/Frontend/Fill_In_With_Postnl_Handler.php` | `https://dil-login.postnl.nl/oauth2/token/`, `https://dil-login.postnl.nl/api/user_info/` | Not in V4 shipment API | SDK OAuth client credentials not equivalent | Old client | Existing browser authorization-code PKCE flow differs from SDK machine-to-machine OAuth |

## 9. Checkout / DeliveryDate Reference

- DeliveryDate is represented through TimeFrame V4 SDK services:
  - `POST /shipment/delivery/v4/timeframe/singleservice`
  - `POST /shipment/delivery/v4/timeframe/multipleservices`
- SDK docs show `$postnl->checkout()->getSingleServiceTimeframe()` and `$postnl->checkout()->getMultipleServicesTimeframe()` for TimeFrame calls; prior code inspection found `singleTimeframe()` / `multipleTimeframes()` on `Client` — verify before use.
- Checkout behavior is covered through TimeFrame + Locations:
  - delivery-day options: TimeFrame services.
  - pickup-point options: Locations services (`$postnl->locations()` per SDK docs).
- No standalone Checkout V4 service is exposed; no standalone DeliveryDate V4 service is exposed.
- Postal-code/address validation remains outside V4; SDK includes a V1 checkout postal-code extension.

## 10. Gaps / Unknowns

| Area | What is known | What is missing | Impact |
|---|---|---|---|
| product/options -> V4 productData/services mapping | V4 examples use `shipmentType`, `services`, `returnOptions`, `deliveryWindow`; plugin uses old `ProductCodeDelivery` and `ProductOptions` from `src/Helper/Mapping.php` | Exact mapping for every old product code/characteristic/option to V4 fields | Cannot safely migrate all labels without a mapping table and test cases |
| Smart Returns replacement or keep-old decision | Postman has Smart Returns examples on `POST /shipment/delivery/v4/return/generate`; SDK covers return generate | Whether old `POST /shipment/v2_2/label/` Smart Returns behavior is fully replaced by V4 return generate | Keep old client or hybrid until equivalence confirmed |
| activatereturn replacement or keep-old decision | Postman has `POST /shipment/delivery/v4/return/activate`; SDK has no service | Request/response model and SDK extension decision | Requires custom extension or old client fallback |
| OAuth requirement per endpoint | Postman V4 shipment/return/activate examples show `apikey`; SDK supports API key and OAuth client credentials | Which V4 endpoints require OAuth instead of API key is not explicit in inspected sources | Mark endpoint auth as API-key evidenced; OAuth requirement unclear |
| SDK docs/code mismatches that affect method names | SDK docs and prior code inspection disagree on facade accessor names for Locations and TimeFrame services (see Section 11 for detail); Confirming method name is inconsistent within the SDK docs themselves | If wrong method is called the call fails at runtime | Verify `src/Client/Client.php` and interfaces before any implementation |
| Checkout/DeliveryDate standalone absence | SDK docs show `$postnl->checkout()` for TimeFrame calls; no standalone `deliveryDate()` accessor documented | No direct V4 deliveryDate or single checkout service; TimeFrame + Locations must be aggregated | Checkout migration must call TimeFrame and Locations separately |
| `services.adrLq` casing | SDK `PayloadKey::adrLq = 'adrLq'`; extracted Postman field appeared as `services.adrlq` | Exact API casing accepted by V4 | Verify before sending ADR LQ requests |
| `labelSettings.outputType` casing | SDK enum values lowercase; Postman examples show `PDF` | Whether API accepts both or only one casing | Normalize through SDK enum unless PostNL confirms otherwise |
| Barcode request field naming | SDK constructor uses `serieStart`/`serieEnd`; SDK `fromArray()` uses `seriesStart`/`seriesEnd` (with trailing `s`) | Whether the API key (serialized form) is `serieStart` or `seriesStart` | Verify against installed SDK `src/Service/Barcode/V4/Request/BarcodeRequest.php` |
| `LabelType` enum completeness | SDK docs confirm `Label`, `labelinthebox`, `shipmentandreturnlabel` | Values `retourLabel`, `CN23`, `CommercialInvoice` listed in prior reference but not found in provided SDK docs | Check SDK `src/Enums/Payload/LabelType.php` before mapping |
| Locations and TimeFrame `Client` facade | SDK docs consistently show `$postnl->locations()` and `$postnl->checkout()` | Prior code inspection found `addressLocations()`, `coordinateLocations()`, `singleTimeframe()`, `multipleTimeframes()` on `Client` — if those are absent, SDK doc examples will fail | Verify `src/Client/Client.php` before implementation |

## 11. Docs / Code Mismatches

| Area | Docs say | Code exposes | Impact | Evidence |
|---|---|---|---|---|
| TimeFrame namespaces | Docs import `Postnl\Sdk\Service\Checkout\V4\Request\SingleServiceTimeframeRequest` and `MultipleServicesTimeframeRequest` | Code classes are under `Postnl\Sdk\Service\SingleServiceTimeframe\V4\Request` and `Postnl\Sdk\Service\MultipleServicesTimeframe\V4\Request` | Wrong imports fail | `docs/TimeFrame/README.md`, `src/Service/SingleServiceTimeframe/V4/Request/SingleServiceTimeframeRequest.php` |
| TimeFrame facade | SDK docs consistently use `$postnl->checkout()->getSingleServiceTimeframe()` and `$postnl->checkout()->getMultipleServicesTimeframe()`; one complete example uses `$postnl->checkout()->multipleTimeframes()` | Prior code inspection found `$postnl->singleTimeframe()->getTimeframe()` and `$postnl->multipleTimeframes()->getTimeframes()` on `Client` — if `checkout()` is absent from `Client`, all SDK doc examples fail | Verify `src/Client/Client.php` before writing integration code | `docs/TimeFrame/README.md` |
| Locations facade | SDK docs use `$postnl->locations()->getPickupLocationsByAddress()` and `$postnl->locations()->getNearPickupLocationsByCoordinates()` | Prior code inspection found `$postnl->addressLocations()->getNearestByAddress()` and `$postnl->coordinateLocations()->getNearestByCoordinates()` on `Client` — if `locations()` is absent, SDK doc examples fail | Verify `src/Client/Client.php` before writing integration code | `docs/Locations/README.md` |
| Confirming method name | SDK docs examples use `confirmShipmentPreAnnouncement()` in most code blocks but the complete example uses `preAnnounceShipment()` — SDK docs are internally inconsistent | Prior code inspection found interface exposes `preAnnounceShipment()` | Use `preAnnounceShipment()` per prior code inspection; confirm against installed SDK | `docs/Confirming/README.md` |
| retry builder method | SDK docs show `withRetryPolicy(new ExponentialBackoffRetryPolicy(maxRetries: 3, baseDelayMs: 1000, maxDelayMs: 10000))` | Prior code inspection found `ClientBuilder::withRetry(RetryConfig $config)` — different method name and signature | Verify `src/Client/ClientBuilder.php` before calling | `docs/Configuration/README.md` |
| enum namespaces | Docs examples may imply checkout-specific request namespace | Code payload enums are under `Postnl\Sdk\Enums\Payload` | Use code namespaces for imports | `src/Enums/Payload/*` |
| Checkout references | TimeFrame SDK docs use `$postnl->checkout()` as the facade accessor for timeframe calls; Extension docs reference V1 checkout postal-code extension only | Prior code inspection of `Client` found no `checkout()` method — if absent, TimeFrame calls via `$postnl->checkout()` will fail | Verify `src/Client/Client.php`; the postal-code V1 extension is accessed via `Client::extensions()` regardless | `docs/TimeFrame/README.md`, `docs/Extension/README.md`, `src/Client/Client.php` |
| Extension cache context | SDK Extension docs explicitly show `$context->cache` as valid and document `ServiceContext` as exposing `transport`, `apiVersion`, `identity`, `logger`, `cache`, `payloadMapper` | Prior code inspection of `ServiceContext.php` did not find a `cache` property | Verify `src/Service/ServiceContext.php` against installed SDK | `docs/Extension/README.md` |

## 12. Error Handling Reference

- Base/catch-all SDK interfaces/classes:
  - `Postnl\Sdk\Exception\PostnlExceptionInterface`
  - `Postnl\Sdk\Exception\PostnlSdkException`
  - `Postnl\Sdk\Exception\HttpSdkException`
- Client/auth/validation/rate-limit/timeout/server exceptions:
  - `Exception\Client\AuthenticationException`
  - `Exception\Client\ClientException`
  - `Exception\Client\ValidationException`
  - `Exception\Client\RateLimitException`
  - `Exception\Client\TimeoutException`
  - `Exception\Server\ServerException`
  - `Exception\Auth\AuthException`
  - `Exception\Transport\TransportException`
  - `Exception\Retry\RetryExhaustedException`
- Normalizers/parsers:
  - `Exception\ExceptionNormalizer`
  - `Exception\Data\Parser\ProblemDetailsParser`
  - `Exception\Data\Parser\Rfc9457JsonParser`
  - `Exception\Data\Parser\LegacyFaultListParser`
  - `Exception\Data\Parser\PlainTextParser`
  - `Exception\Data\Parser\FaultsNormalizer`
  - `Exception\Data\Parser\FieldErrorsNormalizer`
- Additional exception: `SchemaMismatchException` — thrown when PostNL returns a response that does not conform to the SDK schema (required field absent or wrong type); implements `ServerErrorExceptionInterface`; exposes `$targetClass` and `$field` for structured logging.
- Horizontal marker interfaces (catch-by-intent, all extend `PostnlExceptionInterface`):
  - `AuthExceptionInterface` — any auth failure (pre-request or HTTP 401/403)
  - `TransportExceptionInterface` — pre-response network failure; exposes `getFailureReason(): TransportFailureReason`
  - `ClientErrorExceptionInterface` — any HTTP 4xx
  - `ServerErrorExceptionInterface` — server-side failure or API schema break
- Log redaction: SDK ships `RedactionRegistry` with default rules — sensitive fields (email, name, postal code, street, house number, label binary) are auto-redacted from all log output; customize via `withRedactionRegistry()` on `ClientBuilder`; disable with `NullRedactionRegistry`.
- Integration boundary rule:
  - Catch `PostnlExceptionInterface` at the plugin SDK wrapper boundary.
  - Convert SDK exceptions to existing plugin admin/customer error surfaces.
  - Log sanitized request/response metadata only; preserve old plugin masking of label binary data in `src/Logger.php`.

## 13. Auth / Environment Reference

| Item | Value | Evidence |
|---|---|---|
| API key header | `apikey` | `src/Auth/ApiKeyRequestAuthenticator.php`, `src/Enums/HttpHeader.php`, Postman examples |
| OAuth client credentials support | `Auth::oauthClientCredentials()`, `Postnl::oauthClient()`, `Postnl::sandboxOauthClient()` | `src/Auth/Auth.php`, `src/Client/Postnl.php` |
| OAuth browser PKCE support | Not provided by SDK; plugin implements Fill In With PostNL manually | `postnl-for-woocommerce-org/src/Frontend/Fill_In_With_Postnl_Handler.php` |
| sandbox base URL | `https://api-sandbox.postnl.nl/` | `ClientBuilder::SANDBOX_BASE_URI` |
| production base URL | `https://api.postnl.nl/` | `ClientBuilder::PRODUCTION_BASE_URI` |
| SDK env vars (auth) | `SDK_POSTNL_API_KEY`, `SDK_POSTNL_CLIENT_ID`, `SDK_POSTNL_CLIENT_SECRET`, `SDK_POSTNL_OAUTH_TOKEN_URL`, `SDK_POSTNL_IS_SANDBOX` | `docs/Configuration/README.md` |
| SDK env vars (operational) | `SDK_POSTNL_API_VERSION` (1/4/5, default 4), `SDK_POSTNL_MAX_RETRIES` (default 3; 0 to disable), `SDK_POSTNL_RETRY_DELAY_MS` (default 1000), `SDK_POSTNL_MAX_RETRY_DELAY_MS` (default 10000), `SDK_POSTNL_SOURCE_SYSTEM`, `SDK_POSTNL_CUSTOMER_NUMBER`, `SDK_POSTNL_CUSTOMER_CODE`, `SDK_POSTNL_MIN_LOG_LEVEL`, `SDK_POSTNL_LOGGER_CLASS_PATH` | `docs/Configuration/README.md` |
| SDK env vars (cache) | `SDK_POSTNL_CACHE_STORE_TYPE` (auto/redis/memcached/file/array), `SDK_POSTNL_CACHE_TTL` (default 3600), `SDK_POSTNL_CACHE_PREFIX` (default `sdk_postnl_`), `SDK_POSTNL_REDIS_HOST`/`_PORT`/`_PASSWORD`/`_DATABASE`, `SDK_POSTNL_MEMCACHED_HOST`/`_PORT`, `SDK_POSTNL_FILE_CACHE_DIR` | `docs/Configuration/README.md` |
| where configured | `Auth::fromEnv()`, `Environment::readFactorySecrets()`, `ClientBuilder::withAuth()`, `ClientBuilder::withSandbox()` | `src/Auth/Auth.php`, `src/Config/Environment.php`, `src/Client/ClientBuilder.php` |
| Postman apikey endpoints | All three Postman V4 paths show `apikey`: `/labelconfirm`, `/return/generate`, `/return/activate` | `postnl-docs/PostNL Future Proof V4 API's.postman_collection.json` |
| OAuth requirement per endpoint | Unclear | Sources show apikey examples and SDK OAuth support, but no endpoint-level OAuth requirement matrix |
| HTTP-layer response caching | `CachingPlugin::create(cache, ttl, allowedEndpoints, logger)` registered via `withPlugin()` on `ClientBuilder`; auth headers excluded from cache key; supports per-tenant `keyPrefix`; useful for `/timeframe/` and `/locations/` | `docs/Configuration/README.md` |

## 14. Developer Checklist

### SDK integration task checklist

- Uses a plugin-owned SDK wrapper/boundary, not direct SDK calls scattered through UI/admin classes.
- Builds SDK client with API key from existing plugin settings and correct sandbox flag.
- Catches `PostnlExceptionInterface` at the boundary.
- Logs sanitized request/response data; does not expose API keys, OAuth tokens, label binary content, or PII unnecessarily.
- Preserves existing old-client fallback for flows marked `Old client`, `Hybrid`, `Needs confirmation`, or `Needs mapping`.
- Uses code-exposed SDK method names, not mismatched docs examples.

### Barcode task checklist

- Uses `Client::barcode()->generateBarcode(BarcodeRequest)` for V4 barcode calls.
- Maps old `Type`, `Serie`, `Range`, `CustomerCode`, `CustomerNumber` deliberately to V4 `serieStart`, `serieEnd`, `numberOfBarcodes`, `customerCode`, `customerNumber`.
- Keeps old barcode client if barcode range/type mapping is not confirmed.
- Does not log generated barcode batches with credentials.

### Locations task checklist

- Verifies the correct facade method name against `src/Client/Client.php` before calling (`locations()` per SDK docs vs. `addressLocations()`/`coordinateLocations()` per prior code inspection).
- Uses `getPickupLocationsByAddress()` for address searches and `getNearPickupLocationsByCoordinates()` for coordinate searches (per SDK docs).
- Maps checkout pickup UI data to `PickUpLocationsResponse` without changing frontend expected fields.
- Preserves `numberOfLocations`, `locationType`, and `pickUpDate` behavior from plugin settings.

### TimeFrame task checklist

- Verifies the correct facade method name against `src/Client/Client.php` before calling (`checkout()` per SDK docs vs. `singleTimeframe()`/`multipleTimeframes()` per prior code inspection).
- Uses `getSingleServiceTimeframe()` and `getMultipleServicesTimeframe()` (per SDK docs; `multipleTimeframes()` also appears in one SDK doc example).
- Represents delivery dates through TimeFrame V4 responses.
- Maps old checkout options `Daytime`, `Evening`, `08:00-12:00` only where V4 service fields support them.
- Keeps checkout fee/selection behavior compatible with `src/Frontend/Container.php`.

### Shipment/Labelling task checklist

- Uses `shipmentDelivery()->labelConfirm()` for combined label+confirm flow.
- Uses `labelling()->requestLabel()` only when label-only behavior is intended.
- Maps old `ProductCodeDelivery` and `ProductOptions` to V4 `shipmentType`, `services`, `deliveryWindow`, and `returnOptions`.
- Preserves multicollo, insured, signature, delivery code, age check, ADR LQ, pickup, letterbox, EU, and ROW behavior.
- Preserves label output format behavior or documents deliberate SDK enum normalization.

### Return task checklist

- Uses `returnShipment()->generateReturn()` for confirmed V4 return-generate behavior.
- Maps return address, return barcode, return period, valuable return, and print method.
- Does not migrate Smart Returns unless replacement behavior is confirmed.
- Keeps old return label behavior where V4 mapping is incomplete.

### Checkout replacement task checklist

- Treats checkout replacement as TimeFrame + Locations aggregation.
- Does not assume a standalone `checkout()` SDK service exists.
- Does not assume a standalone DeliveryDate V4 SDK service exists.
- Preserves frontend response shape consumed by delivery-day and pickup-point UI.
- Keeps postal-code validation outside V4.

### Fallback/old-client task checklist

- Keeps old client for Fill In With PostNL OAuth browser PKCE flow.
- Keeps or extends old client for activatereturn until V4 SDK extension/request model is confirmed.
- Keeps old client for postal-code check unless SDK extension `GET` behavior is verified against current plugin `POST` behavior.
- Keeps old client for Smart Returns until `return/generate` equivalence is confirmed.
- Provides explicit logging that identifies whether SDK or old client handled the request, without secrets.

## 15. Source Index

| Source type | Important files |
|---|---|
| Postman collection file | `postnl-docs/PostNL Future Proof V4 API's.postman_collection.json` |
| SDK composer.json | `postnl-sdk-audit/vendor/postnl/api-client-sdk/composer.json` |
| SDK main client/factory files | `src/Client/Postnl.php`; `src/Client/Client.php`; `src/Client/PostnlClientInterface.php`; `src/Client/ClientBuilder.php`; `src/Service/ServiceFactory.php`; `src/Service/ServiceContext.php` |
| SDK service files | `src/Service/ShipmentDelivery/ShipmentDelivery.php`; `src/Service/ReturnShipment/ReturnShipment.php`; `src/Service/Labelling/Labelling.php`; `src/Service/Confirming/Confirming.php`; `src/Service/Barcode/Barcode.php`; `src/Service/NearAddressPickupLocations/NearAddressPickupLocations.php`; `src/Service/NearCoordinatesPickupLocations/NearCoordinatesPickupLocations.php`; `src/Service/SingleServiceTimeframe/SingleServiceTimeframe.php`; `src/Service/MultipleServicesTimeframe/MultipleServicesTimeframe.php`; `src/Service/Checkout/V1/Extension/PostalCodeCheckExtension.php` |
| SDK request/response folders | `src/RequestData/V4`; `src/Service/*/V4/Request`; `src/Service/*/V4/Response`; `src/ResponseData/V4`; `src/ResponseData/V1` |
| SDK docs folders | `docs/Barcode`; `docs/ShipmentDelivery`; `docs/Labelling`; `docs/Confirming`; `docs/ReturnShipment`; `docs/Locations`; `docs/TimeFrame`; `docs/Extension`; `docs/ErrorHandling`; `docs/Configuration` |
| plugin current API client files | `postnl-for-woocommerce-org/src/Rest_API/Base.php`; `src/Rest_API/Barcode/Client.php`; `src/Rest_API/Shipping/Client.php`; `src/Rest_API/Return_Label/Client.php`; `src/Rest_API/Letterbox/Client.php`; `src/Rest_API/Checkout/Client.php`; `src/Rest_API/Postcode_Check/Client.php`; `src/Rest_API/Smart_Returns/Client.php`; `src/Rest_API/Shipment_and_Return/Client.php` |
| plugin checkout/frontend files | `src/Frontend/Container.php`; `src/Frontend/Delivery_Day.php`; `src/Frontend/Dropoff_Points.php`; `src/Checkout_Blocks/Extend_Store_Endpoint.php`; `src/Checkout_Blocks/Extend_Block_Core.php` |
| plugin Fill In With PostNL files | `src/Frontend/Fill_In_With_Postnl.php`; `src/Frontend/Fill_In_With_Postnl_Handler.php`; `src/Shipping_Method/Fill_In_With_PostNL_Settings.php` |
| plugin mapping/settings files | `src/Helper/Mapping.php`; `src/Shipping_Method/Settings.php`; `src/Utils.php`; `src/Logger.php` |
