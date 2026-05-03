<?php

use App\Jobs\ImportPricingCustomExportJob;
use App\Models\Card;
use App\Models\File;
use App\Models\Product;
use App\Models\User;
use App\Services\Catalog\PricingCustomExportImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake();
});

function pricingCustomExportHeader(): string
{
    return implode(',', [
        '"TCGplayer Id"',
        '"Product Line"',
        '"Set Name"',
        '"Product Name"',
        '"Title"',
        '"Number"',
        '"Rarity"',
        '"Condition"',
        '"TCG Market Price"',
        '"TCG Direct Low"',
        '"TCG Low Price With Shipping"',
        '"TCG Low Price"',
        '"Total Quantity"',
        '"Add to Quantity"',
        '"TCG Marketplace Price"',
        '"Photo URL"',
    ]);
}

function fakeValidPricingCustomExport(): UploadedFile
{
    $body = pricingCustomExportHeader()."\n"
        ."4941474,Magic,Welcome to Rathe,Boltyn,,BOL001,Rare,Near Mint,1.50,,,1.20,1,0,1.50,\n";

    return UploadedFile::fake()->createWithContent('PricingCustomExport.csv', $body);
}

test('unauthenticated POST to /catalog/upload is rejected', function () {
    auth()->logout();

    $this->post(route('catalog.upload'), [
        'file' => fakeValidPricingCustomExport(),
    ])->assertRedirect(route('login'));
});

test('valid CSV upload stores a files row and dispatches the importer job', function () {
    Bus::fake();
    Cache::flush();

    $this->from(route('catalog.index'))
        ->post(route('catalog.upload'), [
            'file' => fakeValidPricingCustomExport(),
        ])
        ->assertRedirect(route('catalog.index'));

    expect(File::query()->count())->toBe(1);

    $file = File::query()->firstOrFail();
    expect($file->type)->toBe('import')
        ->and($file->original_filename)->toBe('PricingCustomExport.csv')
        ->and($file->file_path)->toContain('imports/pricing/');

    Storage::assertExists($file->file_path);
    Bus::assertDispatched(ImportPricingCustomExportJob::class, fn ($job) => $job->fileId === $file->id);
    expect(Cache::has(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY))->toBeTrue();
});

test('non-CSV file is rejected with 422', function () {
    Bus::fake();

    $this->from(route('catalog.index'))
        ->post(route('catalog.upload'), [
            'file' => UploadedFile::fake()->createWithContent('notes.txt', 'hello world'),
        ])
        ->assertSessionHasErrors('file');

    expect(File::query()->count())->toBe(0);
    Bus::assertNothingDispatched();
});

test('malformed CSV (missing required columns) still creates a files row but flashes upload error', function () {
    Bus::fake();

    $body = "Wrong,Header,Columns\nfoo,bar,baz\n";
    $upload = UploadedFile::fake()->createWithContent('weird.csv', $body);

    $this->from(route('catalog.index'))
        ->post(route('catalog.upload'), ['file' => $upload])
        ->assertRedirect(route('catalog.index'))
        ->assertSessionHas('catalog_upload_error');

    // File row is preserved for inspection per docs/ux/catalog.md#upload-flow.
    expect(File::query()->count())->toBe(1);
    Bus::assertNotDispatched(ImportPricingCustomExportJob::class);
});

test('upload error is exposed to the page via meta.upload_error after a malformed upload', function () {
    Bus::fake();

    $body = "Wrong,Header,Columns\nfoo,bar,baz\n";

    $this->from(route('catalog.index'))->post(route('catalog.upload'), [
        'file' => UploadedFile::fake()->createWithContent('weird.csv', $body),
    ]);

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->where('meta.upload_error', fn ($v) => is_string($v) && str_contains($v, 'Missing columns'))
    );
});

test('the job materializes persisted files and runs the importer end-to-end', function () {
    Cache::flush();

    $body = pricingCustomExportHeader()."\n"
        ."4941474,Magic,Welcome to Rathe,Boltyn,,BOL001,Rare,Near Mint,1.50,,,1.20,1,0,1.50,\n";

    Storage::put('imports/pricing/2026/05/test.csv', $body);

    $file = File::create([
        'type' => 'import',
        'file_path' => 'imports/pricing/2026/05/test.csv',
        'original_filename' => 'PricingCustomExport.csv',
        'uploaded_at' => now(),
    ]);

    Cache::put(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY, true, now()->addHour());

    (new ImportPricingCustomExportJob($file->id))->handle(
        app(PricingCustomExportImporter::class),
    );

    expect(Card::query()->count())->toBe(1)
        ->and(Product::query()->where('name', 'Magic')->exists())->toBeTrue()
        ->and(Cache::has(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY))->toBeFalse();

    $last = Cache::get(ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY);
    expect($last)->toBeArray()
        ->and($last['success'])->toBeTrue()
        ->and($last['rows_processed'])->toBe(1)
        ->and($last['product_label'])->toBe('Magic');
});

test('catalog index page exposes import_in_flight + import_last_result to props', function () {
    Cache::flush();
    Cache::put(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY, true, now()->addHour());

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->where('meta.import_in_flight', true)
            ->where('meta.import_last_result', null)
    );

    Cache::forget(ImportPricingCustomExportJob::IN_FLIGHT_CACHE_KEY);
    Cache::put(
        ImportPricingCustomExportJob::LAST_RESULT_CACHE_KEY,
        [
            'success' => true,
            'rows_processed' => 7,
            'products_touched' => 1,
            'product_label' => 'Magic',
            'completed_at' => '2026-05-03T10:00:00+00:00',
        ],
        now()->addHour(),
    );

    $this->get(route('catalog.index'))->assertInertia(
        fn ($page) => $page
            ->where('meta.import_in_flight', false)
            ->where('meta.import_last_result.success', true)
            ->where('meta.import_last_result.rows_processed', 7)
            ->where('meta.import_last_result.product_label', 'Magic')
    );
});
