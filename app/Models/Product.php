<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'base_price', 'high_price', 'market_offset', 'high_offset', 'priced_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'base_price' => 'integer',
            'high_price' => 'integer',
            'market_offset' => 'integer',
            'high_offset' => 'integer',
            'priced_at' => 'datetime',
        ];
    }

    public function sets(): HasMany
    {
        return $this->hasMany(Set::class);
    }

    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(Card::class, Set::class);
    }
}
