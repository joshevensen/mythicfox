<?php

namespace App\Services\SellerStats;

interface StorefrontFetcher
{
    public function fetchHtml(string $url): string;
}
