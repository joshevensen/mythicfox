<?php

namespace App\Services\Orders;

use App\Models\File;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\Parsers\OrderListParser;
use App\Services\Orders\Parsers\OrderListRow;
use App\Services\Orders\Parsers\PackingSlipLine;
use App\Services\Orders\Parsers\PackingSlipPdfParser;
use App\Services\Orders\Parsers\PullSheetLineItem;
use App\Services\Orders\Parsers\PullSheetParser;
use App\Services\Orders\Parsers\ShippingExportParser;
use App\Services\Orders\Parsers\ShippingExportRow;
use App\Support\FilePath;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class OrderImporter
{
    public function __construct(
        private readonly ?InventoryDecrementer $decrementer = null,
    ) {}

    public function import(OrderImportInput $input): OrderImportResult
    {
        $result = new OrderImportResult;

        // Step 1: persist files. Storage writes intentionally happen outside the
        // DB transaction — orphaned objects are tolerable per the spec; the
        // weekly cleanup job will purge them.
        $orderListFile = $this->persistFile($input->orderListPath, $input->orderListFilename);
        $result->files[] = $orderListFile;
        $shippingFile = $input->shippingExportPath
            ? $this->persistFile($input->shippingExportPath, $input->shippingExportFilename)
            : null;
        if ($shippingFile) {
            $result->files[] = $shippingFile;
        }
        $pullSheetFile = $input->pullSheetPath
            ? $this->persistFile($input->pullSheetPath, $input->pullSheetFilename)
            : null;
        if ($pullSheetFile) {
            $result->files[] = $pullSheetFile;
        }
        $pdfFile = $input->packingSlipPdfPath
            ? $this->persistFile($input->packingSlipPdfPath, $input->packingSlipFilename)
            : null;
        if ($pdfFile) {
            $result->files[] = $pdfFile;
        }

        // Step 2: parse. OrderList failure is fatal; the rest are partial.
        try {
            $orderListRows = (new OrderListParser)->parse($input->orderListPath);
        } catch (Throwable $e) {
            $result->errors[] = 'OrderList: '.$e->getMessage();

            return $result;
        }

        /** @var array<int, ShippingExportRow> $shippingByOrder */
        $shippingByOrder = [];
        if ($input->shippingExportPath) {
            try {
                foreach ((new ShippingExportParser)->parse($input->shippingExportPath) as $row) {
                    $shippingByOrder[$row->tcgplayerOrderNumber] = $row;
                }
            } catch (Throwable $e) {
                $result->errors[] = 'ShippingExport: '.$e->getMessage();
            }
        }

        /** @var array<string, list<PullSheetLineItem>> $pullSheetByOrder */
        $pullSheetByOrder = [];
        if ($input->pullSheetPath) {
            try {
                foreach ((new PullSheetParser)->parse($input->pullSheetPath) as $item) {
                    $pullSheetByOrder[$item->tcgplayerOrderNumber][] = $item;
                }
            } catch (Throwable $e) {
                $result->errors[] = 'PullSheet: '.$e->getMessage();
            }
        }

        /** @var array<string, list<PackingSlipLine>> $pdfByOrder */
        $pdfByOrder = [];
        if ($input->packingSlipPdfPath) {
            try {
                foreach ((new PackingSlipPdfParser)->parse($input->packingSlipPdfPath) as $line) {
                    $pdfByOrder[$line->tcgplayerOrderNumber][] = $line;
                }
            } catch (Throwable $e) {
                $result->errors[] = 'PackingSlips: '.$e->getMessage();
            }
        }

        // Warn for orders that appear in non-OrderList sources but not in OrderList.
        $orderListSet = $orderListRows->pluck('tcgplayerOrderNumber')->flip();
        foreach (array_keys($shippingByOrder) as $orderNumber) {
            if (! isset($orderListSet[$orderNumber])) {
                $result->warnings[] = "ShippingExport order [{$orderNumber}] not found in OrderList; skipped.";
            }
        }
        foreach (array_keys($pullSheetByOrder) as $orderNumber) {
            if (! isset($orderListSet[$orderNumber])) {
                $result->warnings[] = "PullSheet order [{$orderNumber}] not found in OrderList; skipped.";
            }
        }

        // Step 3+4: upsert orders + create order_items for new orders. Single
        // outer transaction; any unexpected failure rolls back the whole batch.
        DB::transaction(function () use ($orderListRows, $shippingByOrder, $pullSheetByOrder, $pdfByOrder, $result) {
            foreach ($orderListRows as $row) {
                $existing = Order::where('tcgplayer_order_number', $row->tcgplayerOrderNumber)
                    ->lockForUpdate()
                    ->first();
                $shipping = $shippingByOrder[$row->tcgplayerOrderNumber] ?? null;

                if ($existing) {
                    $this->updateExisting($existing, $row, $shipping, $result);

                    continue;
                }

                $order = $this->insertNew($row, $shipping);
                $result->ordersInserted++;

                $newItems = $this->createOrderItems(
                    $order,
                    $pullSheetByOrder[$row->tcgplayerOrderNumber] ?? [],
                    $pdfByOrder[$row->tcgplayerOrderNumber] ?? [],
                    $result,
                );

                if ($newItems !== [] && $this->decrementer !== null) {
                    $decrement = $this->decrementer->decrement($order, collect($newItems));
                    $result->lineItemsUnmatchedToInventory += $decrement->unmatched;
                }
            }
        });

        return $result;
    }

    private function persistFile(string $sourcePath, ?string $filename): File
    {
        $original = $filename ?: basename($sourcePath);
        $storagePath = FilePath::build('imports', 'orders', $original);

        Storage::put($storagePath, file_get_contents($sourcePath));

        return File::create([
            'type' => 'import',
            'file_path' => $storagePath,
            'original_filename' => $original,
            'uploaded_at' => Carbon::now(),
        ]);
    }

    private function updateExisting(Order $existing, OrderListRow $row, ?ShippingExportRow $shipping, OrderImportResult $result): void
    {
        $changed = false;

        if ($existing->tcgplayer_status !== $row->tcgplayerStatus) {
            $existing->tcgplayer_status = $row->tcgplayerStatus;
            $changed = true;
        }

        if ($shipping !== null) {
            if ($existing->tracking_number !== $shipping->trackingNumber) {
                $existing->tracking_number = $shipping->trackingNumber;
                $changed = true;
            }
            if ($existing->carrier !== $shipping->carrier) {
                $existing->carrier = $shipping->carrier;
                $changed = true;
            }
        }

        if ($changed) {
            $existing->save();
            $result->ordersUpdated++;
        }
    }

    private function insertNew(OrderListRow $row, ?ShippingExportRow $shipping): Order
    {
        $orderDate = $shipping?->orderDate ?? $row->orderDate;

        return Order::create([
            'tcgplayer_order_number' => $row->tcgplayerOrderNumber,
            'tcgplayer_status' => $row->tcgplayerStatus,
            'buyer_firstname' => $shipping?->buyerFirstname,
            'buyer_lastname' => $shipping?->buyerLastname,
            'buyer_name' => $row->buyerName,
            'address1' => $shipping?->address1,
            'address2' => $shipping?->address2,
            'city' => $shipping?->city,
            'state' => $shipping?->state,
            'postal_code' => $shipping?->postalCode,
            'country' => $shipping?->country,
            'order_date' => $orderDate,
            'shipping_method' => $shipping?->shippingMethod,
            'item_count' => $shipping?->itemCount,
            'product_weight' => $shipping?->productWeight,
            'product_amount' => $row->productAmount,
            'shipping_amount' => $row->shippingAmount,
            'total_amount' => $row->totalAmount,
            'buyer_paid' => $row->buyerPaid,
            'tracking_number' => $shipping?->trackingNumber,
            'carrier' => $shipping?->carrier,
            'imported_at' => Carbon::now(),
        ]);
    }

    /**
     * @param  list<PullSheetLineItem>  $pullSheetItems
     * @param  list<PackingSlipLine>  $pdfLines
     * @return list<OrderItem>
     */
    private function createOrderItems(Order $order, array $pullSheetItems, array $pdfLines, OrderImportResult $result): array
    {
        if ($pullSheetItems === []) {
            return [];
        }

        $created = [];

        foreach ($pullSheetItems as $pullItem) {
            $pdfMatch = $this->matchPdfLine($pdfLines, $pullItem);

            $item = OrderItem::create([
                'order_id' => $order->id,
                'product_line' => $pullItem->productLine,
                'set_name' => $pullItem->setName,
                'product_name' => $pullItem->productName,
                'number' => $pullItem->number,
                'rarity' => $pullItem->rarity,
                'condition' => $pullItem->condition,
                'quantity' => $pullItem->quantity,
                'unit_price' => $pdfMatch?->unitPrice,
                'total_price' => $pdfMatch?->totalPrice,
                'tcgplayer_sku_id' => $pullItem->tcgplayerSkuId,
            ]);

            $result->lineItemsCreated++;
            if ($pdfMatch === null) {
                $result->lineItemsUnmatchedToPdf++;
            }

            $created[] = $item;
        }

        return $created;
    }

    /**
     * @param  list<PackingSlipLine>  $pdfLines
     */
    private function matchPdfLine(array $pdfLines, PullSheetLineItem $pullItem): ?PackingSlipLine
    {
        foreach ($pdfLines as $pdf) {
            if (
                $this->normalize($pdf->productLine) === $this->normalize($pullItem->productLine)
                && $this->normalize($pdf->setName) === $this->normalize($pullItem->setName)
                && $this->normalize($pdf->productName) === $this->normalize($pullItem->productName)
                && $pdf->number === $pullItem->number
                && $this->normalize($pdf->rarity) === $this->normalize($pullItem->rarity)
                && $this->normalize($pdf->condition) === $this->normalize($pullItem->condition)
            ) {
                return $pdf;
            }
        }

        return null;
    }

    /**
     * Normalize for the PDF↔PullSheet match: collapse multiple spaces and
     * remove any space-around-hyphen artifacts ("Free- For- All" vs
     * "Free-For-All") that pdfparser introduces.
     */
    private function normalize(string $value): string
    {
        $clean = preg_replace('/\s+/', ' ', $value) ?? $value;
        $clean = preg_replace('/\s*-\s*/', '-', $clean) ?? $clean;

        return trim($clean);
    }
}
