<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Orders\OrderQueryFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackingSlipController extends Controller
{
    private const BULK_HARD_CAP = 100;

    public function show(Order $order): Response
    {
        return Inertia::render('Orders/PackingSlip', [
            'orders' => [
                $this->presentOrder($order),
            ],
            'placeholder_message' => 'Packing slip rendering is implemented in phase 70.',
        ]);
    }

    public function bulk(Request $request): Response
    {
        $rawIds = (string) $request->query('ids', '');
        $selectAll = $request->query('select_all') === '1';

        if ($rawIds !== '') {
            $orderNumbers = OrderQueryFilters::splitCsv($rawIds);
            abort_if(count($orderNumbers) > self::BULK_HARD_CAP, 413, 'Too many orders selected.');

            $orders = Order::query()
                ->whereIn('tcgplayer_order_number', $orderNumbers)
                ->get()
                ->keyBy('tcgplayer_order_number');

            foreach ($orderNumbers as $number) {
                abort_if(! $orders->has($number), 404, "Unknown order [{$number}].");
            }
        } elseif ($selectAll) {
            // Filter-signature mode: re-run the orders query with the same
            // status + date filters the table is showing and resolve to the
            // matching order numbers. The 100-order cap applies to the count
            // of rows the filter returns.
            $query = OrderQueryFilters::apply(Order::query(), $request);
            $matching = (clone $query)->count();

            abort_if($matching === 0, 404, 'No orders match these filters.');
            abort_if($matching > self::BULK_HARD_CAP, 413, 'Too many orders selected.');

            $orders = $query
                ->orderBy('order_date', 'desc')
                ->get()
                ->keyBy('tcgplayer_order_number');

            $orderNumbers = $orders->keys()->all();
        } else {
            abort(404, 'No orders requested.');
        }

        $payload = collect($orderNumbers)
            ->map(fn (string $number) => $this->presentOrder($orders[$number]))
            ->all();

        return Inertia::render('Orders/PackingSlip', [
            'orders' => $payload,
            'placeholder_message' => 'Packing slip rendering is implemented in phase 70.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'tcgplayer_order_number' => $order->tcgplayer_order_number,
            'buyer_name' => $order->buyer_name,
            'order_date' => $order->order_date?->toDateString(),
        ];
    }
}
