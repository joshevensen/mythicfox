<?php

namespace App\Services\SellerStats;

class StorefrontResult
{
    /**
     * @param  array<int, array{text: string, rating: int|null, author: string|null, date: string|null}>|null  $feedback
     */
    public function __construct(
        public readonly ?float $rating,
        public readonly ?int $reviewCount,
        public readonly ?array $feedback,
    ) {}
}
