<?php

namespace App\Services\Orders\Parsers;

final readonly class PullSheetLineItem
{
    public function __construct(
        public string $tcgplayerOrderNumber,
        public int $quantity,
        public string $productLine,
        public string $setName,
        public string $productName,
        public string $number,
        public string $rarity,
        public string $condition,
        public ?int $tcgplayerSkuId,
    ) {}
}
