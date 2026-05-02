<?php

use App\Models\File;
use Illuminate\Support\Carbon;

test('File::create round-trips with all fields populated', function () {
    $file = File::create([
        'type' => 'import',
        'file_path' => 'imports/orders/2026/03/01HQ-orderlist.csv',
        'original_filename' => 'OrderList.csv',
        'uploaded_at' => Carbon::parse('2026-03-15 10:00:00'),
    ]);

    $reloaded = $file->fresh();

    expect($reloaded->type)->toBe('import');
    expect($reloaded->file_path)->toBe('imports/orders/2026/03/01HQ-orderlist.csv');
    expect($reloaded->original_filename)->toBe('OrderList.csv');
    expect($reloaded->uploaded_at->equalTo(Carbon::parse('2026-03-15 10:00:00')))->toBeTrue();
    expect($reloaded->expired_at)->toBeNull();
});

test('the (type, uploaded_at) index supports the cleanup-job query', function () {
    File::create([
        'type' => 'import',
        'file_path' => 'imports/orders/2025/12/abc-old.csv',
        'original_filename' => 'old.csv',
        'uploaded_at' => Carbon::now()->subDays(120),
    ]);
    File::create([
        'type' => 'import',
        'file_path' => 'imports/orders/2026/02/def-recent.csv',
        'original_filename' => 'recent.csv',
        'uploaded_at' => Carbon::now()->subDays(30),
    ]);
    File::create([
        'type' => 'export',
        'file_path' => 'exports/pricing/2025/12/ghi-pricing.csv',
        'original_filename' => 'pricing.csv',
        'uploaded_at' => Carbon::now()->subDays(120),
    ]);

    $stale = File::where('type', 'import')
        ->where('uploaded_at', '<', Carbon::now()->subDays(90))
        ->whereNull('expired_at')
        ->get();

    expect($stale)->toHaveCount(1);
    expect($stale->first()->original_filename)->toBe('old.csv');
});
