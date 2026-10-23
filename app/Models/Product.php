<?php

namespace App\Models;

use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

#[Fillable(['name', 'display_name', 'base_price', 'high_price', 'market_offset', 'high_offset', 'priced_at'])]
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

    /**
     * The name shown in the UI: display_name when set, otherwise the TCGPlayer name.
     * `name` stays the import/sync match key and must never be edited.
     */
    protected function displayLabel(): Attribute
    {
        return Attribute::get(fn (): string => $this->display_name ?? $this->name);
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
