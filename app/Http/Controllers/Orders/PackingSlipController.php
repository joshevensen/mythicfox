<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Orders\OrderQueryFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Inertia\Inertia;
use Inertia\Response;

class PackingSlipController extends Controller
{
    public const MAX_CARDS_PER_SHEET = 20;

    private const BULK_HARD_CAP = 100;

    public function show(Order $order): Response
    {
        return Inertia::render('Orders/PackingSlip', [
            'orders' => [$this->presentOrder($order)],
            'returnAddress' => config('brand.return_address'),
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
                ->with('items')
                ->get()
                ->keyBy('tcgplayer_order_number');

            foreach ($orderNumbers as $number) {
                abort_if(! $orders->has($number), 404, "Unknown order [{$number}].");
            }
        } elseif ($selectAll) {
            $query = OrderQueryFilters::apply(Order::query(), $request);
            $matching = (clone $query)->count();

            abort_if($matching === 0, 404, 'No orders match these filters.');
            abort_if($matching > self::BULK_HARD_CAP, 413, 'Too many orders selected.');

            $orders = $query
                ->orderBy('order_date', 'desc')
                ->with('items')
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
            'returnAddress' => config('brand.return_address'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentOrder(Order $order): array
    {
        $items = $order->relationLoaded('items')
            ? $order->items->sortBy('id')->values()
            : $order->items()->orderBy('id')->get();

        return [
            'id' => $order->id,
            'tcgplayer_order_number' => $order->tcgplayer_order_number,
            'buyer_name' => $order->buyer_name,
            'address1' => $order->address1,
            'address2' => $order->address2,
            'city' => $order->city,
            'state' => $order->state,
            'postal_code' => $order->postal_code,
            'country' => $order->country,
            'order_date' => $order->order_date?->format('M j, Y'),
            'total_amount_formatted' => Number::currency(($order->total_amount ?? 0) / 100, 'USD', 'en'),
            'items' => $items->map(fn (OrderItem $item) => [
                'product_line' => $item->product_line,
                'product_name' => $item->product_name,
                'set_name' => $item->set_name,
                'condition' => $item->condition,
                'quantity' => $item->quantity,
            ])->all(),
        ];
    }
}
