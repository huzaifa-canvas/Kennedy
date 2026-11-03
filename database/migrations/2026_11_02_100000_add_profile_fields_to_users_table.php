<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->after('email');
            $table->enum('role', ['user', 'admin'])->default('user')->after('password')->index();
            $table->enum('status', ['active', 'suspended'])->default('active')->after('role')->index();
            $table->char('currency', 3)->default('USD')->after('status');
            $table->string('timezone', 64)->default('UTC')->after('currency');
            $table->enum('pay_frequency', ['weekly', 'biweekly', 'semimonthly', 'monthly', 'varies'])->nullable()->after('timezone');
            $table->date('next_payday')->nullable()->after('pay_frequency');
            $table->decimal('estimated_paycheck_amount', 12, 2)->nullable()->after('next_payday');
            $table->enum('allowance_view', ['weekly', 'daily'])->default('weekly')->after('estimated_paycheck_amount');
            $table->boolean('notifications_enabled')->default(true)->after('allowance_view');
            $table->timestamp('onboarding_completed_at')->nullable()->after('notifications_enabled');
            $table->timestamp('last_login_at')->nullable()->after('onboarding_completed_at');
            $table->string('last_login_ip', 45)->nullable()->after('last_login_at');
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'phone', 'role', 'status', 'currency', 'timezone', 'pay_frequency',
                'next_payday', 'estimated_paycheck_amount', 'allowance_view',
                'notifications_enabled', 'onboarding_completed_at', 'last_login_at',
                'last_login_ip', 'deleted_at',
            ]);
        });
    }
};
