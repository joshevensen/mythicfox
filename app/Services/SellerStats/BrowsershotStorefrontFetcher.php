<?php

namespace App\Services\SellerStats;

use Spatie\Browsershot\Browsershot;

class BrowsershotStorefrontFetcher implements StorefrontFetcher
{
    public function fetchHtml(string $url): string
    {
        return Browsershot::url($url)
            ->setUserAgent('Mythic Fox Games / seller-stats-bot')
            ->timeout(30_000)
            ->bodyHtml();
    }
}
