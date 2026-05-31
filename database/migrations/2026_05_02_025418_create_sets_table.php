<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Sets are product-scoped catalog releases or sealed product groupings.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->integer('base_price')->nullable();
            $table->integer('high_price')->nullable();
            $table->integer('market_offset')->nullable();
            $table->integer('high_offset')->nullable();
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sets');
    }
};
