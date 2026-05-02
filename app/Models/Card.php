<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['set_id', 'tcgplayer_id', 'product_name', 'number', 'rarity', 'condition', 'market_price', 'low_price'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tcgplayer_id' => 'integer',
            'market_price' => 'integer',
            'low_price' => 'integer',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(CardSet::class, 'set_id');
    }

    public function inventory(): HasOne
    {
        return $this->hasOne(Inventory::class);
    }
}
