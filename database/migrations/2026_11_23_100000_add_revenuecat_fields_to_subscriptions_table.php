<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Everything RevenueCat tells us about a store subscription, so the app can
 * show trial dates, auto-renew state and billing-grace without guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('store', 20)->nullable()->after('provider')->comment('app_store, play_store, promotional, manual');
            $table->string('product_id', 191)->nullable()->after('provider_subscription_id');
            $table->string('period_type', 20)->nullable()->after('product_id')->comment('trial, intro, normal');
            $table->string('environment', 20)->nullable()->after('period_type')->comment('production, sandbox');
            $table->boolean('will_renew')->default(true)->after('environment');
            $table->timestamp('trial_started_at')->nullable()->after('started_at');
            $table->timestamp('trial_ends_at')->nullable()->after('trial_started_at');
            $table->timestamp('billing_issue_detected_at')->nullable()->after('cancelled_at');
            $table->timestamp('grace_period_ends_at')->nullable()->after('billing_issue_detected_at');
            $table->string('management_url', 255)->nullable()->after('grace_period_ends_at');
            $table->timestamp('last_event_at')->nullable()->after('management_url')->comment('Newest RevenueCat event applied, guards against out-of-order webhooks');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'store', 'product_id', 'period_type', 'environment', 'will_renew',
                'trial_started_at', 'trial_ends_at', 'billing_issue_detected_at',
                'grace_period_ends_at', 'management_url', 'last_event_at',
            ]);
        });
    }
};
