<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('set_id')->constrained('sets')->restrictOnDelete();
            $table->string('name');
            $table->string('number');
            $table->string('rarity');
            $table->timestamps();

            $table->unique(['set_id', 'name', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cards');
    }
};
