<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class PublicHomepageController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('public/Home', [
            'tcgplayerStorefrontUrl' => config('services.tcgplayer.storefront_url'),
            'sellerStats' => null,
            'showBuyersSay' => false,
        ]);
    }
}
