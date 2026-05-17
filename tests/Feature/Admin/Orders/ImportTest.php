<?php

use App\Jobs\ImportOrdersJob;
use App\Models\File;
use App\Models\Order;
use App\Models\User;
use App\Services\Orders\OrderImporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    Storage::fake();
});

test('unauthenticated POST to /orders/import is rejected', function () {
    auth()->logout();

    $this->post(route('orders.import'), [
        'orderlist' => UploadedFile::fake()->createWithContent(
            'orderlist.csv',
            "Order #,Status\nABC,Completed - Paid\n",
        ),
    ])->assertRedirect(route('login'));
});

test('valid POST with OrderList stores a files row and dispatches the importer job', function () {
    Bus::fake();

    $this->post(route('orders.import'), [
        'orderlist' => UploadedFile::fake()->createWithContent(
            'orderlist.csv',
            "Order #,Status\nABC,Completed - Paid\n",
        ),
    ])->assertRedirect();

    expect(File::query()->where('type', 'import')->count())->toBe(1);

    $file = File::query()->where('type', 'import')->first();
    expect($file->original_filename)->toBe('orderlist.csv');
    expect($file->file_path)->toContain('imports/orders/');

    Bus::assertDispatched(ImportOrdersJob::class, function (ImportOrdersJob $job) use ($file) {
        return $job->orderListFileId === $file->id
            && $job->shippingExportFileId === null
            && $job->pullSheetFileId === null
            && $job->packingSlipFileId === null;
    });
});

test('all four files persist as separate files rows and the job receives all four IDs', function () {
    Bus::fake();

    $this->post(route('orders.import'), [
        'orderlist' => UploadedFile::fake()->createWithContent('orderlist.csv', 'a'),
        'shipping_export' => UploadedFile::fake()->createWithContent('shipping.csv', 'b'),
        'pull_sheet' => UploadedFile::fake()->createWithContent('pullsheet.csv', 'c'),
        'packing_slips' => UploadedFile::fake()->create('packingslips.pdf', 1, 'application/pdf'),
    ])->assertRedirect();

    expect(File::query()->where('type', 'import')->count())->toBe(4);

    Bus::assertDispatched(ImportOrdersJob::class, function (ImportOrdersJob $job) {
        return $job->orderListFileId !== null
            && $job->shippingExportFileId !== null
            && $job->pullSheetFileId !== null
            && $job->packingSlipFileId !== null;
    });
});

test('POST without OrderList returns 422', function () {
    Bus::fake();

    $this->post(route('orders.import'), [])
        ->assertSessionHasErrors('orderlist');

    Bus::assertNotDispatched(ImportOrdersJob::class);
    expect(File::query()->count())->toBe(0);
});

test('POST with non-CSV OrderList rejects via mime validation', function () {
    Bus::fake();

    $this->post(route('orders.import'), [
        'orderlist' => UploadedFile::fake()->create('not-a-csv.exe', 1),
    ])->assertSessionHasErrors('orderlist');

    Bus::assertNotDispatched(ImportOrdersJob::class);
});

test('POST sets the import-in-flight cache flag visible to the orders index controller', function () {
    Bus::fake();

    $this->post(route('orders.import'), [
        'orderlist' => UploadedFile::fake()->createWithContent('orderlist.csv', 'a'),
    ])->assertRedirect();

    expect(Cache::has(ImportOrdersJob::IN_FLIGHT_CACHE_KEY))->toBeTrue();

    $this->get(route('orders.index'))->assertInertia(
        fn ($page) => $page->where('meta.import_in_flight', true)
    );
});

test('malformed CSV upload still creates a files row (parse failures surface from the job, not the controller)', function () {
    Bus::fake();

    $this->post(route('orders.import'), [
        // The controller does not parse — it only checks MIME/ext. The job
        // (faked here) is responsible for parse errors. So a valid-extension
        // but content-malformed CSV still produces a files row.
        'orderlist' => UploadedFile::fake()->createWithContent(
            'broken.csv',
            'not really csv at all',
        ),
    ])->assertRedirect();

    expect(File::query()->where('type', 'import')->count())->toBe(1);
    Bus::assertDispatched(ImportOrdersJob::class);
});

test('the job materializes persisted files and runs the importer end-to-end', function () {
    // Real run (no Bus::fake) using the canonical fixture set so we exercise
    // the importer's parse-and-upsert path through the job. This is the
    // closest we have to the production flow short of a real queue worker.
    $orderList = base_path('tests/fixtures/orders/merge-orderlist.csv');

    Storage::put('imports/orders/2026/05/orderlist.csv', file_get_contents($orderList));

    $file = File::create([
        'type' => 'import',
        'file_path' => 'imports/orders/2026/05/orderlist.csv',
        'original_filename' => 'orderlist.csv',
        'uploaded_at' => now(),
    ]);

    (new ImportOrdersJob($file->id))->handle(app(OrderImporter::class));

    expect(Order::query()->count())->toBeGreaterThan(0);
    expect(Cache::has(ImportOrdersJob::IN_FLIGHT_CACHE_KEY))->toBeFalse();
});

test('the dashboard shortcut /orders?import=1 is rendered as a route the modal listens for', function () {
    // The modal-open behaviour itself is client-side; here we only assert that
    // the URL is reachable and renders the page (so the SPA mount can read
    // ?import=1 and open the dialog).
    $this->get(route('orders.index', ['import' => 1]))->assertOk();
});
