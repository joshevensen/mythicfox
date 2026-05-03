<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
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
        $orderNumbers = array_values(array_filter(
            array_map('trim', explode(',', $rawIds)),
            fn (string $v) => $v !== '',
        ));

        abort_if($orderNumbers === [], 404, 'No orders requested.');
        abort_if(count($orderNumbers) > self::BULK_HARD_CAP, 413, 'Too many orders selected.');

        $orders = Order::query()
            ->whereIn('tcgplayer_order_number', $orderNumbers)
            ->get()
            ->keyBy('tcgplayer_order_number');

        foreach ($orderNumbers as $number) {
            abort_if(! $orders->has($number), 404, "Unknown order [{$number}].");
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
