<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('tagline')->nullable();
            $table->decimal('price', 8, 2)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->enum('billing_period', ['monthly', 'yearly', 'lifetime'])->default('monthly');
            $table->string('store_product_id')->nullable();
            $table->unsignedInteger('bill_limit')->nullable()->comment('null = unlimited');
            $table->unsignedInteger('income_source_limit')->nullable();
            $table->unsignedInteger('savings_jar_limit')->nullable();
            $table->unsignedInteger('history_days')->nullable();
            $table->boolean('allows_smart_splits')->default(false);
            $table->boolean('allows_custom_reminders')->default(false);
            $table->boolean('allows_advanced_recurrence')->default(false);
            $table->boolean('allows_calendar_themes')->default(false);
            $table->json('features')->nullable();
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
