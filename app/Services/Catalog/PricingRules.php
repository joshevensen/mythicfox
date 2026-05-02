<?php

namespace App\Services\Catalog;

class PricingRules
{
    public function __construct(
        public readonly int $basePrice,
        public readonly int $highPrice,
        public readonly int $marketOffset,
        public readonly int $highOffset,
    ) {}
}
