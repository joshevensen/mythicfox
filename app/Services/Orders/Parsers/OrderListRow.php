<?php

namespace App\Services\Orders\Parsers;

use Carbon\CarbonImmutable;

final readonly class OrderListRow
{
    public function __construct(
        public string $tcgplayerOrderNumber,
        public string $tcgplayerStatus,
        public string $buyerName,
        public CarbonImmutable $orderDate,
        public int $productAmount,
        public int $shippingAmount,
        public int $totalAmount,
        public bool $buyerPaid,
    ) {}
}
