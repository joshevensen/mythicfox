<?php

use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderImporter;
use App\Services\Orders\OrderImportInput;
use App\Services\Orders\OrderImportResult;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

function mergeFixture(string $name): string
{
    return base_path("tests/fixtures/orders/{$name}");
}

function buildInput(?string $orderList = null, ?string $shipping = null, ?string $pull = null, ?string $pdf = null): OrderImportInput
{
    return new OrderImportInput(
        orderListPath: $orderList ?? mergeFixture('merge-orderlist.csv'),
        shippingExportPath: $shipping,
        pullSheetPath: $pull,
        packingSlipPdfPath: $pdf,
    );
}

test('happy-path import inserts orders, creates line items, populates PDF prices', function () {
    $input = buildInput(
        orderList: mergeFixture('merge-orderlist.csv'),
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
        pdf: mergeFixture('packing-slips-sample.pdf'),
    );

    $result = (new OrderImporter)->import($input);

    expect($result->ordersInserted)->toBe(2);
    expect($result->ordersUpdated)->toBe(0);

    $thomas = Order::where('tcgplayer_order_number', '623394E9-23CAFE-565FC')->firstOrFail();
    expect($thomas->buyer_firstname)->toBe('Thomas');
    expect($thomas->city)->toBe('Newfield');
    expect($thomas->state)->toBe('NY');
    expect($thomas->total_amount)->toBe(889);

    $line = $thomas->items()->firstOrFail();
    expect($line->product_name)->toBe('Beast Within');
    expect($line->unit_price)->toBe(690);
    expect($line->total_price)->toBe(690);
});

test('canceled order with no ShippingExport row is inserted with null shipping fields', function () {
    $result = (new OrderImporter)->import(buildInput(shipping: mergeFixture('merge-shipping.csv')));

    $canceled = Order::where('tcgplayer_order_number', '623394E9-AAAAAA-BBBBB')->firstOrFail();
    expect($canceled->tcgplayer_status)->toBe('Canceled');
    expect($canceled->address1)->toBeNull();
    expect($canceled->state)->toBeNull();
    expect($canceled->buyer_firstname)->toBeNull();
});

test('order in ShippingExport but not in OrderList is skipped with a warning', function () {
    $result = (new OrderImporter)->import(buildInput(shipping: mergeFixture('merge-shipping.csv')));

    expect(Order::where('tcgplayer_order_number', '623394E9-ZIP4-EXTRA')->exists())->toBeFalse();
    expect(implode(' ', $result->warnings))->toContain('623394E9-ZIP4-EXTRA');
});

test('re-import of the same batch is a no-op for orders and order_items', function () {
    $input = buildInput(
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
        pdf: mergeFixture('packing-slips-sample.pdf'),
    );

    $first = (new OrderImporter)->import($input);
    expect($first->ordersInserted)->toBe(2);

    $second = (new OrderImporter)->import($input);
    expect($second->ordersInserted)->toBe(0);
    expect($second->ordersUpdated)->toBe(0);
    expect(Order::count())->toBe(2);
    expect(OrderItem::count())->toBe(1);
});

test('status flip on re-import updates only mutable fields, leaves order_items untouched', function () {
    $input = buildInput(
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
    );
    (new OrderImporter)->import($input);

    $modifiedOrderList = sys_get_temp_dir().'/orderlist-flipped.csv';
    file_put_contents($modifiedOrderList, str_replace(
        '"Completed - Paid"',
        '"Canceled"',
        file_get_contents(mergeFixture('merge-orderlist.csv')),
    ));

    $itemBefore = OrderItem::firstOrFail();
    $itemBeforeUpdatedAt = $itemBefore->updated_at;

    $second = (new OrderImporter)->import(new OrderImportInput(
        orderListPath: $modifiedOrderList,
        shippingExportPath: mergeFixture('merge-shipping.csv'),
        pullSheetPath: mergeFixture('merge-pullsheet.csv'),
    ));

    expect($second->ordersUpdated)->toBeGreaterThan(0);

    $thomas = Order::where('tcgplayer_order_number', '623394E9-23CAFE-565FC')->firstOrFail();
    expect($thomas->tcgplayer_status)->toBe('Canceled');

    $itemAfter = OrderItem::firstOrFail();
    expect($itemAfter->updated_at->equalTo($itemBeforeUpdatedAt))->toBeTrue();

    @unlink($modifiedOrderList);
});

test('null line prices are filled on re-import when the PDF is now provided', function () {
    $input = buildInput(
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
    );
    (new OrderImporter)->import($input);

    $line = OrderItem::firstOrFail();
    expect($line->unit_price)->toBeNull();

    $secondInput = buildInput(
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
        pdf: mergeFixture('packing-slips-sample.pdf'),
    );
    (new OrderImporter)->import($secondInput);

    expect($line->refresh()->unit_price)->toBe(690);
});

test('OrderList missing from input is fatal and produces no DB writes', function () {
    try {
        (new OrderImporter)->import(new OrderImportInput(orderListPath: '/no/such/orderlist.csv'));
        $this->fail('expected a fatal error for missing OrderList');
    } catch (Throwable) {
        // expected
    }

    expect(Order::count())->toBe(0);
});

test('every uploaded file produces a files row at imports/orders/...', function () {
    $input = buildInput(
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
        pdf: mergeFixture('packing-slips-sample.pdf'),
    );
    (new OrderImporter)->import($input);

    $files = File::where('type', 'import')->get();
    expect($files)->toHaveCount(4);

    foreach ($files as $file) {
        expect($file->file_path)->toStartWith('imports/orders/');
        Storage::assertExists($file->file_path);
    }
});

test('OrderImportResult::summaryLine reports inserted and updated counts', function () {
    $result = new OrderImportResult(
        ordersInserted: 5,
        ordersUpdated: 2,
        lineItemsCreated: 12,
    );

    expect($result->summaryLine())->toBe('Imported 7 orders (5 new, 2 updated).');
});

test('OrderImportResult::summaryLine handles the only-new case without unmatched suffix', function () {
    $result = new OrderImportResult(ordersInserted: 1);
    expect($result->summaryLine())->toBe('Imported 1 order (1 new, 0 updated).');
});

// ── Intelligent Mail barcode (IMb) ────────────────────────────

const IMB_ORDER_A = '623394E9-44ECCD-BE247';
const IMB_ORDER_B = '623394E9-85D84F-CA3FC';
const IMB_BARCODE_A = 'FDATDDDFTFDFDATFTAFFTFFDFTFTTADADFFTTTTFTATAFDFTTDTFDFAAFAFTFTADF';
const IMB_BARCODE_B = 'FFFFTDDAFDFAATDFDTFAADDTDFFATTATDTDTDDFAFFDDAFFFADDFFATFDFDATATDF';

/**
 * Writes a temporary OrderList CSV listing both orders present in the IMb packing-slip fixture.
 */
function imbOrderListCsv(): string
{
    $path = sys_get_temp_dir().'/orderlist-imb-'.uniqid().'.csv';

    file_put_contents($path, implode("\n", [
        'Order #,Buyer Name,Order Date,Status,Shipping Type,Product Amt,Shipping Amt,Total Amt,Buyer Paid,Carrier Information',
        '"623394E9-44ECCD-BE247","Connor Vanatta","Tuesday, 27 October 2026","Completed - Paid","Standard","6.07","1.31","7.38","True"',
        '"623394E9-85D84F-CA3FC","Matthew Feeney","Wednesday, 4 November 2026","Completed - Paid","Standard","0.44","1.31","1.75","True"',
    ])."\n");

    return $path;
}

test('IMb: importing with the IMb packing slip stores each order\'s barcode on insert', function () {
    $orderList = imbOrderListCsv();

    $result = (new OrderImporter)->import(buildInput(
        orderList: $orderList,
        pdf: mergeFixture('packing-slips-imb-sample.pdf'),
    ));

    expect($result->ordersInserted)->toBe(2);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_A);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_B);

    @unlink($orderList);
});

test('IMb: identical re-import keeps barcodes and does not count orders as updated', function () {
    $orderList = imbOrderListCsv();
    $input = buildInput(orderList: $orderList, pdf: mergeFixture('packing-slips-imb-sample.pdf'));

    (new OrderImporter)->import($input);
    $second = (new OrderImporter)->import($input);

    expect($second->ordersInserted)->toBe(0);
    expect($second->ordersUpdated)->toBe(0);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_A);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_B);

    @unlink($orderList);
});

test('IMb: re-import with the IMb packing slip fills barcodes on existing orders and counts them as updated', function () {
    $orderList = imbOrderListCsv();

    $first = (new OrderImporter)->import(buildInput(orderList: $orderList));
    expect($first->ordersInserted)->toBe(2);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBeNull();
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBeNull();

    $second = (new OrderImporter)->import(buildInput(
        orderList: $orderList,
        pdf: mergeFixture('packing-slips-imb-sample.pdf'),
    ));

    expect($second->ordersInserted)->toBe(0);
    expect($second->ordersUpdated)->toBe(2);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_A);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_B);

    @unlink($orderList);
});

test('IMb: re-import without a PDF never overwrites a stored barcode with null', function () {
    $orderList = imbOrderListCsv();

    (new OrderImporter)->import(buildInput(orderList: $orderList, pdf: mergeFixture('packing-slips-imb-sample.pdf')));

    $second = (new OrderImporter)->import(buildInput(orderList: $orderList));

    expect($second->ordersUpdated)->toBe(0);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_A);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_B);

    @unlink($orderList);
});

test('IMb: re-import with an old-format (barcode-less) PDF never overwrites a stored barcode with null', function () {
    $orderList = imbOrderListCsv();

    (new OrderImporter)->import(buildInput(orderList: $orderList, pdf: mergeFixture('packing-slips-imb-sample.pdf')));

    (new OrderImporter)->import(buildInput(orderList: $orderList, pdf: mergeFixture('packing-slips-sample.pdf')));

    expect(Order::where('tcgplayer_order_number', IMB_ORDER_A)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_A);
    expect(Order::where('tcgplayer_order_number', IMB_ORDER_B)->firstOrFail()->imb_barcode)->toBe(IMB_BARCODE_B);

    @unlink($orderList);
});

test('IMb: happy-path import with the old-format PDF leaves imb_barcode null', function () {
    (new OrderImporter)->import(buildInput(
        orderList: mergeFixture('merge-orderlist.csv'),
        shipping: mergeFixture('merge-shipping.csv'),
        pull: mergeFixture('merge-pullsheet.csv'),
        pdf: mergeFixture('packing-slips-sample.pdf'),
    ));

    $thomas = Order::where('tcgplayer_order_number', '623394E9-23CAFE-565FC')->firstOrFail();
    expect($thomas->getAttributes())->toHaveKey('imb_barcode');
    expect($thomas->imb_barcode)->toBeNull();
});
