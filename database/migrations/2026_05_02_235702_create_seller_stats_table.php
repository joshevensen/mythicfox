<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_stats', function (Blueprint $table) {
            $table->id();
            $table->decimal('rating', 2, 1)->nullable();
            $table->integer('review_count')->nullable();
            $table->json('feedback')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->text('last_error')->nullable();
            $table->integer('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_stats');
    }
};
