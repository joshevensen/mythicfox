<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained('cards')->restrictOnDelete();
            $table->string('finish');
            $table->integer('tcgplayer_id')->nullable()->unique();
            $table->integer('justtcg_id')->nullable();
            $table->json('other_ids')->nullable();
            $table->string('image_url')->nullable();
            $table->integer('market_price')->nullable();
            $table->integer('low_price')->nullable();
            $table->timestamps();

            $table->unique(['card_id', 'finish']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printings');
    }
};
