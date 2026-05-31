<?php

namespace App\Models;

use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['set_id', 'name', 'number', 'rarity'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    public function set(): BelongsTo
    {
        return $this->belongsTo(Set::class, 'set_id');
    }

    public function printings(): HasMany
    {
        return $this->hasMany(Printing::class);
    }
}
