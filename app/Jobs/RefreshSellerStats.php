<?php

namespace App\Jobs;

use App\Models\SellerStats;
use App\Services\SellerStats\StorefrontFetcher;
use App\Services\SellerStats\TcgplayerStorefrontParser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RefreshSellerStats implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const IN_FLIGHT_CACHE_KEY = 'seller-stats:refreshing';

    public int $timeout = 120;

    public function handle(StorefrontFetcher $fetcher, TcgplayerStorefrontParser $parser): void
    {
        try {
            $stats = SellerStats::query()->orderBy('id')->first()
                ?? SellerStats::query()->create([]);

            $url = (string) config('services.tcgplayer.storefront_url', '');

            $html = $fetcher->fetchHtml($url);
            $result = $parser->parse($html);

            if ($result->rating === null) {
                throw new \RuntimeException('Parser returned no rating — selector may have changed.');
            }

            $updates = [
                'rating' => $result->rating,
                'review_count' => $result->reviewCount,
                'scraped_at' => Carbon::now(),
                'last_attempt_at' => Carbon::now(),
                'last_error' => null,
                'consecutive_failures' => 0,
            ];

            // Only overwrite feedback when the parser found comment text.
            if ($result->feedback !== null && $result->feedback !== []) {
                $updates['feedback'] = $result->feedback;
            }

            $stats->forceFill($updates)->save();

            Log::info('Seller stats refresh succeeded.', [
                'rating' => $result->rating,
                'review_count' => $result->reviewCount,
            ]);
        } catch (\Throwable $e) {
            $this->handleFailure($e);
        } finally {
            Cache::forget(self::IN_FLIGHT_CACHE_KEY);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        // Safety net for unexpected errors outside the handle() catch block.
        Cache::forget(self::IN_FLIGHT_CACHE_KEY);
        $this->handleFailure($exception);
    }

    private function handleFailure(?\Throwable $e): void
    {
        $stats = SellerStats::query()->orderBy('id')->first();

        if ($stats === null) {
            Log::warning('Seller stats refresh failed before singleton could be created.', [
                'error' => $e?->getMessage(),
            ]);

            return;
        }

        $message = $e !== null
            ? substr(get_class($e).': '.$e->getMessage(), 0, 500)
            : 'Unknown error';

        $stats->forceFill([
            'last_attempt_at' => Carbon::now(),
            'last_error' => $message,
            'consecutive_failures' => $stats->consecutive_failures + 1,
        ])->save();

        Log::warning('Seller stats refresh failed.', ['error' => $message]);
    }
}
