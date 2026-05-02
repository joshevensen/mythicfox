<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('product_line');
            $table->string('set_name');
            $table->string('product_name');
            $table->string('number');
            $table->string('rarity');
            $table->string('condition');
            $table->integer('quantity');
            $table->integer('unit_price')->nullable();
            $table->integer('total_price')->nullable();
            $table->integer('tcgplayer_sku_id')->nullable();
            $table->timestamps();

            // Match key for the PDF-line join (20-008) and inventory decrement (20-009).
            // Postgres permits the wider seven-field index; if growth pushes it past
            // 8 KB, narrow to (order_id, product_name, number, condition) and let the
            // importer scan within that bucket.
            $table->index([
                'order_id',
                'product_line',
                'set_name',
                'product_name',
                'number',
                'rarity',
                'condition',
            ], 'order_items_match_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
