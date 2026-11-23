<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * When a Pro user drops back to Free, anything over the Free limits is
 * archived rather than deleted. archived_reason tells a downgrade archive
 * (restorable on re-upgrade) apart from the user pausing an item themselves.
 */
return new class extends Migration
{
    private const TABLES = ['bills', 'income_sources', 'savings_jars'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->timestamp('archived_at')->nullable();
                $table->string('archived_reason', 32)->nullable();
                $table->index(['user_id', 'archived_reason']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('plan_selection_required_at')->nullable()
                ->comment('Set on downgrade when data exceeded Free limits; cleared once the user picks what stays active');
        });
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['user_id', 'archived_reason']);
                $table->dropColumn(['archived_at', 'archived_reason']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('plan_selection_required_at');
        });
    }
};
