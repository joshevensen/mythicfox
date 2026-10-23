<?php

namespace App\Models;

use Database\Factories\PrintingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['card_id', 'finish', 'tcgplayer_id', 'justtcg_id', 'other_ids', 'image_url', 'nm_market_price', 'nm_low_price', 'lp_market_price', 'lp_low_price', 'mp_market_price', 'mp_low_price'])]
class Printing extends Model
{
    /** @use HasFactory<PrintingFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'tcgplayer_id' => 'integer',
            'justtcg_id' => 'integer',
            'other_ids' => 'array',
            'nm_market_price' => 'integer',
            'nm_low_price' => 'integer',
            'lp_market_price' => 'integer',
            'lp_low_price' => 'integer',
            'mp_market_price' => 'integer',
            'mp_low_price' => 'integer',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    protected function set(): Attribute
    {
        return Attribute::get(fn () => $this->card?->set);
    }
}
