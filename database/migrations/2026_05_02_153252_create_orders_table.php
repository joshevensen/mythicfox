<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('tcgplayer_order_number')->unique();
            $table->string('tcgplayer_status');
            $table->string('buyer_firstname')->nullable();
            $table->string('buyer_lastname')->nullable();
            $table->string('buyer_name');
            $table->string('address1')->nullable();
            $table->string('address2')->nullable();
            $table->string('city')->nullable();
            $table->string('state', 2)->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country')->nullable();
            $table->date('order_date');
            $table->string('shipping_method')->nullable();
            $table->integer('item_count')->nullable();
            $table->decimal('product_weight', 8, 2)->nullable();
            $table->integer('product_amount');
            $table->integer('shipping_amount');
            $table->integer('total_amount');
            $table->boolean('buyer_paid');
            $table->string('tracking_number')->nullable();
            $table->string('carrier')->nullable();
            $table->timestamp('imported_at');
            $table->timestamps();

            $table->index('order_date');
            $table->index('buyer_name');
            $table->index('tcgplayer_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
