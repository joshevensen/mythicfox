<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printings', function (Blueprint $table) {
            $table->integer('nm_market_price')->nullable()->after('image_url');
            $table->integer('nm_low_price')->nullable()->after('nm_market_price');
            $table->integer('lp_market_price')->nullable()->after('nm_low_price');
            $table->integer('lp_low_price')->nullable()->after('lp_market_price');
            $table->integer('mp_market_price')->nullable()->after('lp_low_price');
            $table->integer('mp_low_price')->nullable()->after('mp_market_price');
        });

        Schema::table('printings', function (Blueprint $table) {
            $table->dropColumn(['market_price', 'low_price']);
        });
    }

    public function down(): void
    {
        Schema::table('printings', function (Blueprint $table) {
            $table->integer('market_price')->nullable()->after('image_url');
            $table->integer('low_price')->nullable()->after('market_price');
        });

        Schema::table('printings', function (Blueprint $table) {
            $table->dropColumn([
                'nm_market_price',
                'nm_low_price',
                'lp_market_price',
                'lp_low_price',
                'mp_market_price',
                'mp_low_price',
            ]);
        });
    }
};
