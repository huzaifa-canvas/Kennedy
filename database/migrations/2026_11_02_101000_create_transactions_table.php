<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['income', 'expense'])->index();
            $table->string('title');
            $table->decimal('amount', 12, 2);
            $table->date('occurred_on')->index();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_occurrence_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bill_category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('income_source_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('paycheck_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('savings_jar_id')->nullable()->constrained()->nullOnDelete();
            $table->string('icon', 64)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'occurred_on']);
            $table->index(['user_id', 'type', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
