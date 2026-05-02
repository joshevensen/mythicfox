<?php

namespace App\Exceptions\OrderImport;

use RuntimeException;

class WrongSellerException extends RuntimeException
{
    public function __construct(
        public readonly string $orderNumber,
        public readonly string $expectedSellerId,
    ) {
        parent::__construct(
            "Order [{$this->orderNumber}] does not start with the configured TCGPlayer seller ID [{$this->expectedSellerId}]."
        );
    }
}
