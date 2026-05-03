<?php

use App\Http\Controllers\Settings\FilesController;
use App\Http\Controllers\Settings\PricingRulesController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\SellerStatsController;
use App\Http\Controllers\Settings\SettingsController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::get('settings', [SettingsController::class, 'show'])->name('settings');
    Route::patch('settings/products/{product}/pricing-rules', [PricingRulesController::class, 'updateProduct'])
        ->name('settings.products.pricing-rules.update');
    Route::patch('settings/sets/{set}/pricing-rules', [PricingRulesController::class, 'updateSet'])
        ->name('settings.sets.pricing-rules.update');
    Route::get('settings/files/{file}/download', [FilesController::class, 'download'])
        ->name('settings.files.download');
    Route::post('settings/seller-stats/refresh', [SellerStatsController::class, 'refresh'])
        ->name('settings.seller-stats.refresh');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('settings/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('settings/security', [SecurityController::class, 'edit'])->name('security.edit');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});
