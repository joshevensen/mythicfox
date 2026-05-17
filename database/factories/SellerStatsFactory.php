<?php

namespace Database\Factories;

use App\Models\SellerStats;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SellerStats>
 */
class SellerStatsFactory extends Factory
{
    protected $model = SellerStats::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rating' => 4.9,
            'review_count' => 312,
            'feedback' => [],
            'scraped_at' => Carbon::now(),
            'last_attempt_at' => Carbon::now(),
            'last_error' => null,
            'consecutive_failures' => 0,
        ];
    }

    public function fresh(): self
    {
        return $this->state(fn () => [
            'scraped_at' => Carbon::now()->subHours(6),
            'last_attempt_at' => Carbon::now()->subHours(6),
        ]);
    }

    public function stale(): self
    {
        return $this->state(fn () => [
            'scraped_at' => Carbon::now()->subDays(20),
            'last_attempt_at' => Carbon::now()->subDays(20),
        ]);
    }

    public function neverScraped(): self
    {
        return $this->state(fn () => [
            'scraped_at' => null,
            'last_attempt_at' => null,
        ]);
    }

    public function withFeedback(): self
    {
        return $this->state(fn () => [
            'feedback' => [
                ['text' => 'Cards arrived NM as described, fast ship.', 'rating' => 5, 'author' => 'magicmike', 'date' => '2026-04-22'],
                ['text' => 'Solid packaging, no damage.', 'rating' => 5, 'author' => 'lorcanafan', 'date' => '2026-04-18'],
                ['text' => 'Will buy from again.', 'rating' => 5, 'author' => 'fabplayer', 'date' => '2026-04-12'],
            ],
        ]);
    }
}
