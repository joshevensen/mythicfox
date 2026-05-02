<?php

namespace App\Models;

use Database\Factories\InventoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'quantity', 'calculated_price', 'override_price', 'last_exported_price'])]
class Inventory extends Model
{
    /** @use HasFactory<InventoryFactory> */
    use HasFactory;

    protected $table = 'inventory';

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'calculated_price' => 'integer',
            'override_price' => 'integer',
            'last_exported_price' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function effectivePrice(): Attribute
    {
        return Attribute::get(fn () => $this->override_price ?? $this->calculated_price);
    }
}
