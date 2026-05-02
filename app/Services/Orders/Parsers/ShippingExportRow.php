<?php

namespace App\Services\Orders\Parsers;

use Carbon\CarbonImmutable;

final readonly class ShippingExportRow
{
    public function __construct(
        public string $tcgplayerOrderNumber,
        public string $buyerFirstname,
        public string $buyerLastname,
        public ?string $address1,
        public ?string $address2,
        public ?string $city,
        public ?string $state,
        public ?string $postalCode,
        public ?string $country,
        public CarbonImmutable $orderDate,
        public ?string $shippingMethod,
        public ?int $itemCount,
        public ?float $productWeight,
        public ?string $trackingNumber,
        public ?string $carrier,
    ) {}
}
