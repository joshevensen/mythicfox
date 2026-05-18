<?php

namespace App\Http\Controllers\Orders;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderItemsController extends Controller
{
    public function index(Order $order): JsonResponse
    {
        $items = $order->items()
            ->orderBy('product_line')
            ->orderBy('set_name')
            ->orderBy('product_name')
            ->get()
            ->map(fn ($item) => [
                'id' => $item->id,
                'product_line' => $item->product_line,
                'set_name' => $item->set_name,
                'product_name' => $item->product_name,
                'number' => $item->number,
                'condition' => $item->condition,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'total_price' => $item->total_price,
            ])
            ->all();

        return response()->json(['data' => $items]);
    }
}
