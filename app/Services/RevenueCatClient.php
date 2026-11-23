<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Thin wrapper over RevenueCat's REST API (v1).
 *
 * RevenueCat is the source of truth for every store subscription. Reading
 * the subscriber from here, rather than trusting the app or a single webhook
 * payload, keeps us correct when webhooks arrive late or out of order.
 */
class RevenueCatClient
{
    public function isConfigured(): bool
    {
        return (bool) config('services.revenuecat.secret_api_key');
    }

    /**
     * The "subscriber" object for an app user id, or null when the API is
     * not configured or unreachable.
     *
     * @return array<string, mixed>|null
     */
    public function subscriber(string $appUserId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $url = rtrim((string) config('services.revenuecat.api_base'), '/')
            . '/subscribers/' . rawurlencode($appUserId);

        try {
            $response = Http::withToken((string) config('services.revenuecat.secret_api_key'))
                ->acceptJson()
                ->timeout(10)
                ->retry(2, 300, throw: false)
                ->get($url);
        } catch (Throwable $e) {
            Log::warning('RevenueCat API unreachable', ['app_user_id' => $appUserId, 'error' => $e->getMessage()]);

            return null;
        }

        if ($response->failed()) {
            Log::warning('RevenueCat API error', ['app_user_id' => $appUserId, 'status' => $response->status()]);

            return null;
        }

        $subscriber = $response->json('subscriber');

        return is_array($subscriber) ? $subscriber : null;
    }
}
