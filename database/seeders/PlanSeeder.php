<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Free plus one Pro tier billed two ways (Monthly / Yearly). Both Pro
 * products carry a 14-day free trial, configured as an introductory offer in
 * App Store Connect and Google Play and surfaced through RevenueCat.
 *
 * Product ids below are placeholders: they must match the products created
 * in the stores and attached to the "pro" entitlement in RevenueCat. They can
 * also be edited from the admin panel (Plans).
 */
class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $proLimits = [
            'bill_limit' => null,
            'income_source_limit' => null,
            'savings_jar_limit' => null,
            'history_days' => null,
            'allows_smart_splits' => true,
            'allows_custom_reminders' => true,
            'allows_advanced_recurrence' => true,
            'allows_calendar_themes' => true,
            'features' => [
                ['label' => 'Unlimited Bills', 'included' => true],
                ['label' => 'Full Bill Customization', 'included' => true],
                ['label' => 'Smart Splits Across Paychecks', 'included' => true],
                ['label' => 'Multiple Income Sources', 'included' => true],
                ['label' => 'Unlimited Savings Jars (Sinking Funds)', 'included' => true],
                ['label' => '30+ Day Financial History', 'included' => true],
                ['label' => 'Custom Reminders & Notifications', 'included' => true],
                ['label' => 'Calendar Themes & Customization', 'included' => true],
            ],
            'is_default' => false,
            'is_active' => true,
        ];

        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'tagline' => 'The basics, on the house.',
            'price' => 0,
            'billing_period' => 'monthly',
            'store_product_id' => null,
            'play_store_product_id' => null,
            'bill_limit' => 4,
            'income_source_limit' => 2,
            'savings_jar_limit' => 1,
            'history_days' => 30,
            'allows_smart_splits' => false,
            'allows_custom_reminders' => false,
            'allows_advanced_recurrence' => false,
            'allows_calendar_themes' => false,
            'features' => [
                ['label' => 'Up To 4 Bills', 'included' => true],
                ['label' => 'Up To 2 Income Entries', 'included' => true],
                ['label' => '1 Savings Jar', 'included' => true],
                ['label' => 'Basic Calendar View', 'included' => true],
                ['label' => 'Monthly Summary', 'included' => true],
                ['label' => 'Unlimited Bills', 'included' => false],
                ['label' => 'Full Bill Customization', 'included' => false],
                ['label' => 'Smart Splits', 'included' => false],
                ['label' => '30+ Day Insights & History', 'included' => false],
                ['label' => 'Custom Reminders', 'included' => false],
            ],
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // The original single "pro" row becomes Pro Monthly, keeping any
        // subscriptions already linked to it.
        if (! Plan::where('slug', 'pro_monthly')->exists()) {
            Plan::where('slug', 'pro')->update(['slug' => 'pro_monthly']);
        }

        Plan::updateOrCreate(['slug' => 'pro_monthly'], $proLimits + [
            'name' => 'Saverr Pro',
            'tagline' => 'Cancel Anytime. Billed Monthly.',
            'price' => 4.99,
            'billing_period' => 'monthly',
            'store_product_id' => 'saverr_pro_monthly',
            'play_store_product_id' => 'saverr_pro:monthly',
            'sort_order' => 2,
        ]);

        Plan::updateOrCreate(['slug' => 'pro_yearly'], $proLimits + [
            'name' => 'Saverr Pro (Yearly)',
            'tagline' => 'Cancel Anytime. Billed Yearly.',
            'price' => 49.99,
            'billing_period' => 'yearly',
            'store_product_id' => 'saverr_pro_yearly',
            'play_store_product_id' => 'saverr_pro:yearly',
            'sort_order' => 3,
        ]);
    }
}
