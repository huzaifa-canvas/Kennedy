<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paychecks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('income_source_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->decimal('set_aside_amount', 12, 2)->default(0);
            $table->date('received_on')->index();
            $table->timestamps();

            $table->index(['user_id', 'received_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paychecks');
    }
};
