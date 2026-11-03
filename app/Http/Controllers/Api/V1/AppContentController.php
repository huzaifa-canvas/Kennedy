<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillCategoryResource;
use App\Http\Resources\PlanResource;
use App\Models\AppSetting;
use App\Models\BillCategory;
use App\Models\Plan;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;

/**
 * Content the app can fetch before anyone signs in.
 *
 * Onboarding slides and the FAQ list are static copy shipped inside the app,
 * so they are deliberately not served from here.
 */
class AppContentController extends Controller
{
    use ApiResponses;

    public function config(): JsonResponse
    {
        return $this->ok([
            'app_name' => AppSetting::get('app_name', config('app.name')),
            'tagline' => AppSetting::get('app_tagline'),
            'support_email' => AppSetting::get('support_email'),
            'default_currency' => AppSetting::get('default_currency', 'USD'),
            'privacy_policy_url' => AppSetting::get('privacy_policy_url'),
            'terms_url' => AppSetting::get('terms_url'),
            'revenuecat_public_key' => AppSetting::get('revenuecat_public_key'),
            'maintenance_mode' => filter_var(AppSetting::get('maintenance_mode', '0'), FILTER_VALIDATE_BOOLEAN),
            'force_update' => filter_var(AppSetting::get('force_update', '0'), FILTER_VALIDATE_BOOLEAN),
            'minimum_version' => [
                'ios' => AppSetting::get('min_supported_ios_version', '1.0.0'),
                'android' => AppSetting::get('min_supported_android_version', '1.0.0'),
            ],
        ]);
    }

    public function plans(): JsonResponse
    {
        return $this->ok(
            PlanResource::collection(
                Plan::where('is_active', true)->orderBy('sort_order')->get()
            )
        );
    }

    public function billCategories(): JsonResponse
    {
        return $this->ok(
            BillCategoryResource::collection(
                BillCategory::active()->orderBy('sort_order')->get()
            )
        );
    }
}
