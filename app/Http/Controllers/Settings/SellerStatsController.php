<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshSellerStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

class SellerStatsController extends Controller
{
    public function refresh(): RedirectResponse
    {
        Cache::put(RefreshSellerStats::IN_FLIGHT_CACHE_KEY, true, now()->addMinutes(5));

        Bus::dispatchSync(new RefreshSellerStats);

        return back()->with('toast', [
            'kind' => 'info',
            'message' => 'Seller stats refresh requested.',
        ]);
    }
}
