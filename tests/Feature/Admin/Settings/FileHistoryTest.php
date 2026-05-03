<?php

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('unauthenticated visit redirects to login', function () {
    auth()->logout();

    $this->get(route('settings'))->assertRedirect(route('login'));
});

test('authenticated visit lists files default-sorted by uploaded_at desc', function () {
    File::factory()->create([
        'original_filename' => 'older.csv',
        'uploaded_at' => Carbon::parse('2026-01-01 10:00:00'),
    ]);
    File::factory()->create([
        'original_filename' => 'newer.csv',
        'uploaded_at' => Carbon::parse('2026-04-01 10:00:00'),
    ]);

    $this->get(route('settings'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->component('Settings')
            ->has('files.data', 2)
            ->where('files.data.0.original_filename', 'newer.csv')
            ->where('files.data.1.original_filename', 'older.csv')
            ->where('files.meta.per_page', 20)
    );
});

test('filtering by direction=export returns only export rows', function () {
    File::factory()->create(['type' => 'import']);
    File::factory()->create(['type' => 'export']);

    $this->get(route('settings', ['direction' => 'export']))->assertInertia(
        fn ($page) => $page
            ->has('files.data', 1)
            ->where('files.data.0.type', 'export')
    );
});

test('download endpoint returns redirect or stream for an active file', function () {
    Storage::fake();
    $disk = Storage::disk(config('filesystems.default'));
    $disk->put('imports/orders/2026/04/sample.csv', 'col1,col2');

    $file = File::factory()->create([
        'file_path' => 'imports/orders/2026/04/sample.csv',
        'expired_at' => null,
    ]);

    $response = $this->get(route('settings.files.download', $file));

    expect(in_array($response->status(), [200, 302], true))->toBeTrue();
});

test('download endpoint returns 410 for an expired file', function () {
    $file = File::factory()->create([
        'expired_at' => Carbon::now()->subDay(),
    ]);

    $this->get(route('settings.files.download', $file))->assertStatus(410);
});

test('empty state renders when no files exist', function () {
    File::query()->delete();

    $this->get(route('settings'))->assertOk()->assertInertia(
        fn ($page) => $page
            ->where('files.meta.total', 0)
            ->has('files.data', 0)
    );
});
