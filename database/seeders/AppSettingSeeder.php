<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'app_name', 'value' => 'Saverr', 'group' => 'general', 'label' => 'App Name', 'type' => 'string'],
            ['key' => 'app_tagline', 'value' => 'Set Aside Today. Breathe Easy Till Payday', 'group' => 'general', 'label' => 'Tagline', 'type' => 'string'],
            ['key' => 'support_email', 'value' => 'support@billiantbuddy.com', 'group' => 'support', 'label' => 'Support Email', 'type' => 'string'],
            ['key' => 'default_currency', 'value' => 'USD', 'group' => 'general', 'label' => 'Default Currency', 'type' => 'string'],
            ['key' => 'min_supported_ios_version', 'value' => '1.0.0', 'group' => 'mobile', 'label' => 'Minimum iOS Version', 'type' => 'string'],
            ['key' => 'min_supported_android_version', 'value' => '1.0.0', 'group' => 'mobile', 'label' => 'Minimum Android Version', 'type' => 'string'],
            ['key' => 'force_update', 'value' => '0', 'group' => 'mobile', 'label' => 'Force Update', 'type' => 'boolean'],
            ['key' => 'maintenance_mode', 'value' => '0', 'group' => 'mobile', 'label' => 'API Maintenance Mode', 'type' => 'boolean'],
            ['key' => 'privacy_policy_url', 'value' => 'https://billiantbuddy.com/privacy', 'group' => 'legal', 'label' => 'Privacy Policy URL', 'type' => 'string'],
            ['key' => 'terms_url', 'value' => 'https://billiantbuddy.com/terms', 'group' => 'legal', 'label' => 'Terms of Service URL', 'type' => 'string'],
            ['key' => 'revenuecat_public_key', 'value' => '', 'group' => 'billing', 'label' => 'RevenueCat Public Key', 'type' => 'string'],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(['key' => $setting['key']], $setting);
        }
    }
}
