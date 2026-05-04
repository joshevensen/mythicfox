<?php

namespace App\Models;

use Database\Factories\DeckFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['set_id', 'tcgplayer_id', 'product_name', 'rarity', 'condition', 'market_price', 'low_price'])]
class Deck extends Model
{
    /** @use HasFactory<DeckFactory> */
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
}
