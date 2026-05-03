<?php

use App\Http\Controllers\AddCardsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PublicHomepageController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomepageController::class)->name('home');

Route::get('/sitemap.xml', function () {
    $homepage = route('home');
    $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{$homepage}</loc>
    </url>
</urlset>

XML;

    return new Response($body, 200, ['Content-Type' => 'application/xml']);
})->name('sitemap');

Route::get('/robots.txt', function () {
    $sitemap = route('sitemap');
    $body = <<<TXT
User-agent: *
Allow: /
Disallow: /login
Disallow: /dashboard
Disallow: /orders
Disallow: /catalog
Disallow: /inventory
Disallow: /add-cards
Disallow: /settings
Sitemap: {$sitemap}

TXT;

    return new Response($body, 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('add-cards', [AddCardsController::class, 'show'])->name('add-cards');
    Route::post('add-cards', [AddCardsController::class, 'store'])->name('add-cards.store');

    // Phase-60 placeholders. Real implementations land in phase 60 (orders, catalog,
    // inventory). Registered now so Wayfinder generates typed helpers for the
    // dashboard quick-action tiles and top-nav links.
    Route::inertia('orders', 'placeholders/ComingSoon', ['title' => 'Orders'])
        ->name('orders');
    Route::inertia('catalog', 'placeholders/ComingSoon', ['title' => 'Catalog'])
        ->name('catalog');
    Route::inertia('inventory', 'placeholders/ComingSoon', ['title' => 'Inventory'])
        ->name('inventory');
});

require __DIR__.'/settings.php';
