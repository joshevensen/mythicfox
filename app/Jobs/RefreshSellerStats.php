<?php

namespace App\Jobs;

use App\Models\SellerStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

// Stub for the scraper job. Phase 70 task 70-003 replaces handle() with the
// real Browsershot-based TCGPlayer storefront scrape. The stub implementation
// here is intentionally minimal: it stamps last_attempt_at so the Settings
// "Seller stats scraper" card UX (50-005) has something live to show, and it
// honours the in-flight cache flag the controller uses to disable the
// "Refresh now" button.
class RefreshSellerStats implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const IN_FLIGHT_CACHE_KEY = 'seller-stats:refreshing';

    public int $timeout = 60;

    public function handle(): void
    {
        try {
            $stats = SellerStats::query()->orderBy('id')->first()
                ?? SellerStats::query()->create([]);

            $stats->forceFill([
                'last_attempt_at' => Carbon::now(),
            ])->save();
        } finally {
            Cache::forget(self::IN_FLIGHT_CACHE_KEY);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Cache::forget(self::IN_FLIGHT_CACHE_KEY);

        $stats = SellerStats::query()->orderBy('id')->first();

        if ($stats === null) {
            return;
        }

        $stats->forceFill([
            'last_error' => $exception?->getMessage(),
            'consecutive_failures' => $stats->consecutive_failures + 1,
        ])->save();
    }
}
