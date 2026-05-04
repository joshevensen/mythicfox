<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->restrictOnDelete();
            $table->integer('tcgplayer_id')->unique();
            $table->string('product_name');
            $table->string('rarity');
            $table->string('condition');
            $table->integer('market_price')->nullable();
            $table->integer('low_price')->nullable();
            $table->timestamps();

            $table->index(['set_id', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decks');
    }
};
