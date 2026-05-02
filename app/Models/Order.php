<?php

namespace App\Models;

use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tcgplayer_order_number',
    'tcgplayer_status',
    'buyer_firstname',
    'buyer_lastname',
    'buyer_name',
    'address1',
    'address2',
    'city',
    'state',
    'postal_code',
    'country',
    'order_date',
    'shipping_method',
    'item_count',
    'product_weight',
    'product_amount',
    'shipping_amount',
    'total_amount',
    'buyer_paid',
    'tracking_number',
    'carrier',
    'imported_at',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'imported_at' => 'datetime',
            'buyer_paid' => 'boolean',
            'product_weight' => 'decimal:2',
            'item_count' => 'integer',
            'product_amount' => 'integer',
            'shipping_amount' => 'integer',
            'total_amount' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
