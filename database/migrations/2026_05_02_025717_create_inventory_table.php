<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->unique()->constrained()->restrictOnDelete();
            $table->integer('quantity');
            $table->integer('calculated_price')->nullable();
            $table->integer('override_price')->nullable();
            $table->integer('last_exported_price')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE inventory ADD CONSTRAINT inventory_quantity_non_negative CHECK (quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory');
    }
};
