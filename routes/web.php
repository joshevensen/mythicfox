<?php

use App\Http\Controllers\AddCardsController;
use App\Http\Controllers\Catalog\CatalogController;
use App\Http\Controllers\Catalog\CatalogUploadController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Decks\DecksController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\InventoryExportController;
use App\Http\Controllers\Orders\OrderItemsController;
use App\Http\Controllers\Orders\OrdersController;
use App\Http\Controllers\Orders\OrdersImportController;
use App\Http\Controllers\Orders\PackingSlipController;
use App\Http\Controllers\PublicHomepageController;
use App\Http\Controllers\PublicSellToUsController;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

Route::get('/', PublicHomepageController::class)->name('home');
Route::get('/sell-to-us', PublicSellToUsController::class)->name('sell-to-us');

Route::get('/sitemap.xml', function () {
    $homepage = route('home');
    $sellToUs = route('sell-to-us');
    $body = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{$homepage}</loc>
    </url>
    <url>
        <loc>{$sellToUs}</loc>
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
Disallow: /cards
Disallow: /decks
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

    Route::get('orders', [OrdersController::class, 'index'])->name('orders.index');
    Route::post('orders/import', [OrdersImportController::class, 'store'])->name('orders.import');
    Route::get('orders/print', [PackingSlipController::class, 'bulk'])->name('orders.packing-slip.bulk');
    Route::get('orders/{order:tcgplayer_order_number}/packing-slip', [PackingSlipController::class, 'show'])
        ->name('orders.packing-slip.show');
    Route::get('orders/{order:tcgplayer_order_number}/items', [OrderItemsController::class, 'index'])
        ->name('orders.items.index');

    Route::get('cards', [CatalogController::class, 'index'])->name('cards.index');
    Route::post('cards/upload', [CatalogUploadController::class, 'store'])->name('cards.upload');

    Route::get('decks', [DecksController::class, 'index'])->name('decks.index');

    Route::get('inventory', [InventoryController::class, 'index'])->name('inventory.index');
    Route::patch('inventory/{inventory}', [InventoryController::class, 'update'])->name('inventory.update');
    Route::delete('inventory/{inventory}', [InventoryController::class, 'destroy'])->name('inventory.destroy');
    Route::post('inventory/bulk/clear-overrides', [InventoryController::class, 'bulkClearOverrides'])
        ->name('inventory.bulk.clear-overrides');
    Route::post('inventory/bulk/mark-out-of-stock', [InventoryController::class, 'bulkMarkOutOfStock'])
        ->name('inventory.bulk.mark-out-of-stock');
    Route::post('inventory/export/recompute', [InventoryExportController::class, 'recompute'])
        ->name('inventory.export.recompute');
    Route::get('inventory/export/preview', [InventoryExportController::class, 'preview'])
        ->name('inventory.export.preview');
    Route::post('inventory/export/download', [InventoryExportController::class, 'download'])
        ->name('inventory.export.download');
});

require __DIR__.'/settings.php';
