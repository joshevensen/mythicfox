<?php

use Illuminate\Support\Facades\Route;

Route::inertia('/', 'public/Home')->name('home');

Route::middleware(['auth'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
