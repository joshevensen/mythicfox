<?php

use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\CatalogUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Orders\OrderItemsController;
use App\Http\Controllers\Orders\OrdersController;
use App\Http\Controllers\Orders\OrdersImportController;
use App\Http\Controllers\Orders\PackingSlipController;
use App\Http\Controllers\PublicHomepageController;
use App\Http\Controllers\PublicPrivacyController;
use App\Http\Controllers\PublicSellToUsController;
use App\Http\Controllers\PublicTermsController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomepageController::class)->name('home');
Route::get('/sell-to-us', PublicSellToUsController::class)->name('sell-to-us');
Route::get('/terms', PublicTermsController::class)->name('terms');
Route::get('/privacy', PublicPrivacyController::class)->name('privacy');

Route::get('/sitemap.xml', function () {
    $homepage = route('home');
    $sellToUs = route('sell-to-us');
    $terms = route('terms');
    $privacy = route('privacy');
    $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{$homepage}</loc>
    </url>
    <url>
        <loc>{$sellToUs}</loc>
    </url>
    <url>
        <loc>{$terms}</loc>
    </url>
    <url>
        <loc>{$privacy}</loc>
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
Disallow: /settings
Sitemap: {$sitemap}

TXT;

    return new Response($body, 200, ['Content-Type' => 'text/plain']);
})->name('robots');

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::post('orders/import', [OrdersImportController::class, 'store'])->name('orders.import');
    Route::get('orders/print', [PackingSlipController::class, 'bulk'])->name('orders.packing-slip.bulk');
    Route::get('orders/{order:tcgplayer_order_number}/packing-slip', [PackingSlipController::class, 'show'])
        ->name('orders.packing-slip.show');
    Route::get('orders/{order:tcgplayer_order_number}/items', [OrderItemsController::class, 'index'])
        ->name('orders.items.index');

    Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
    Route::post('catalog/upload', [CatalogUploadController::class, 'store'])->name('catalog.upload');
});

require __DIR__.'/settings.php';
