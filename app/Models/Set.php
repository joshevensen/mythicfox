<?php

namespace App\Models;

use Database\Factories\SetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['product_id', 'name', 'base_price', 'high_price', 'market_offset', 'high_offset'])]
class Set extends Model
{
    /** @use HasFactory<SetFactory> */
    use HasFactory;

    protected $table = 'sets';

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'high_price' => 'integer',
            'market_offset' => 'integer',
            'high_offset' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'set_id');
    }
}
