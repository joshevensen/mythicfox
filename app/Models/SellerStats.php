<?php

namespace App\Models;

use Database\Factories\SellerStatsFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'rating',
    'review_count',
    'feedback',
    'scraped_at',
    'last_attempt_at',
    'last_error',
    'consecutive_failures',
])]
class SellerStats extends Model
{
    /** @use HasFactory<SellerStatsFactory> */
    use HasFactory;

    protected $table = 'seller_stats';

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'review_count' => 'integer',
            'feedback' => 'array',
            'scraped_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'consecutive_failures' => 'integer',
        ];
    }
}
