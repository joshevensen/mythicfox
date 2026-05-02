<?php

namespace App\Services\Orders;

use App\Exceptions\OrderImport\WrongSellerException;

/**
 * Validates that a TCGPlayer order number's first hyphen-segment matches the
 * configured TCGPLAYER_SELLER_ID. Comparison is case-insensitive.
 *
 * Fall-through: if config('services.tcgplayer.seller_id') is empty/null,
 * isValid() returns true (skips the check). This avoids breaking the test
 * suite when the env isn't set, and lets the operator opt out by leaving
 * TCGPLAYER_SELLER_ID empty.
 */
class SellerIdValidator
{
    public function isValid(string $orderNumber): bool
    {
        $configured = (string) (config('services.tcgplayer.seller_id') ?? '');
        if ($configured === '') {
            return true;
        }

        $segments = explode('-', $orderNumber, 2);
        if (count($segments) < 2 || $segments[0] === '') {
            return false;
        }

        return strcasecmp($segments[0], $configured) === 0;
    }

    public function assertValid(string $orderNumber): void
    {
        if (! $this->isValid($orderNumber)) {
            throw new WrongSellerException(
                orderNumber: $orderNumber,
                expectedSellerId: (string) (config('services.tcgplayer.seller_id') ?? ''),
            );
        }
    }
}
