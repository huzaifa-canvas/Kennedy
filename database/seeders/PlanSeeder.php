<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'free'], [
            'name' => 'Free',
            'tagline' => 'The basics, on the house.',
            'price' => 0,
            'billing_period' => 'monthly',
            'bill_limit' => 3,
            'income_source_limit' => 1,
            'savings_jar_limit' => 0,
            'history_days' => 30,
            'allows_smart_splits' => false,
            'allows_custom_reminders' => false,
            'allows_advanced_recurrence' => false,
            'allows_calendar_themes' => false,
            'features' => [
                ['label' => 'Up To 3 Bills', 'included' => true],
                ['label' => 'Basic Calendar View', 'included' => true],
                ['label' => 'Monthly Summary', 'included' => true],
                ['label' => 'Unlimited Bills', 'included' => false],
                ['label' => 'Full Bill Customization', 'included' => false],
                ['label' => 'Smart Splits', 'included' => false],
                ['label' => 'Multiple Income Sources', 'included' => false],
                ['label' => 'Savings Jars (Goals)', 'included' => false],
                ['label' => '30+ Day Insights & History', 'included' => false],
                ['label' => 'Custom Reminders', 'included' => false],
            ],
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        Plan::updateOrCreate(['slug' => 'pro'], [
            'name' => 'Brilliant Buddy Pro',
            'tagline' => 'Cancel Anytime. Billed Monthly.',
            'price' => 4.99,
            'billing_period' => 'monthly',
            'store_product_id' => 'bb_pro_monthly',
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
                ['label' => 'Savings Jars (Sinking Funds)', 'included' => true],
                ['label' => '30+ Day Financial History', 'included' => true],
                ['label' => 'Custom Reminders & Notifications', 'included' => true],
                ['label' => 'Calendar Themes & Customization', 'included' => true],
            ],
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 2,
        ]);
    }
}
