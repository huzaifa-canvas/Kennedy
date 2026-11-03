<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bill_occurrences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('due_date')->index();
            $table->enum('status', ['upcoming', 'paid', 'overdue', 'skipped'])->default('upcoming')->index();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['bill_id', 'due_date']);
            $table->index(['user_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bill_occurrences');
    }
};
