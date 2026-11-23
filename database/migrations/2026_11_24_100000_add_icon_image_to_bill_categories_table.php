<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An uploaded icon image for a category (public disk path). When set, the
 * app shows it instead of the Tabler icon named in `icon`, which stays as
 * the fallback.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_categories', function (Blueprint $table) {
            $table->string('icon_image')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('bill_categories', function (Blueprint $table) {
            $table->dropColumn('icon_image');
        });
    }
};
