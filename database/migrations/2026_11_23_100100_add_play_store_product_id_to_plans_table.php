<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('play_store_product_id', 191)->nullable()->after('store_product_id')
                ->comment('Google Play id as RevenueCat reports it, e.g. saverr_pro:monthly');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('play_store_product_id');
        });
    }
};
