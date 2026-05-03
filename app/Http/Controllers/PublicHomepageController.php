<?php

namespace App\Http\Controllers;

use App\Models\SellerStats;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PublicHomepageController extends Controller
{
    public function __invoke(): Response
    {
        $stats = SellerStats::query()->orderBy('id')->first();

        $isFresh = $stats !== null
            && $stats->scraped_at !== null
            && $stats->scraped_at->greaterThanOrEqualTo(Carbon::now()->subDays(14));

        $sellerStats = $isFresh ? [
            'rating' => (float) $stats->rating,
            'review_count' => (int) $stats->review_count,
            'feedback' => array_slice($stats->feedback ?? [], 0, 3),
        ] : null;

        return Inertia::render('public/Home', [
            'tcgplayerStorefrontUrl' => config('services.tcgplayer.storefront_url'),
            'sellerStats' => $sellerStats,
            'showBuyersSay' => $isFresh,
        ]);
    }
}
