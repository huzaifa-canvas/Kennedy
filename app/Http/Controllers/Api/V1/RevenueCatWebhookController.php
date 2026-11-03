<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Server-to-server subscription truth from RevenueCat.
 *
 * Guarded by the shared secret configured in the RevenueCat dashboard and
 * compared in constant time, so a forged call cannot grant Pro.
 */
class RevenueCatWebhookController extends Controller
{
    use ApiResponses;

    private const GRANTING_EVENTS = [
        'INITIAL_PURCHASE', 'RENEWAL', 'PRODUCT_CHANGE', 'UNCANCELLATION', 'NON_RENEWING_PURCHASE',
    ];

    private const REVOKING_EVENTS = ['EXPIRATION', 'BILLING_ISSUE'];

    public function handle(Request $request): JsonResponse
    {
        $secret = config('services.revenuecat.webhook_secret');

        if (! $secret) {
            Log::warning('RevenueCat webhook hit with no secret configured.');

            return $this->fail('Webhook is not configured.', 503);
        }

        $provided = (string) $request->header('Authorization', '');

        if (! hash_equals($secret, $provided)) {
            return $this->fail('Invalid signature.', 401);
        }

        $validated = $request->validate([
            'event' => ['required', 'array'],
            'event.type' => ['required', 'string', 'max:64'],
            'event.app_user_id' => ['required', 'string', 'max:191'],
            'event.product_id' => ['nullable', 'string', 'max:191'],
            'event.id' => ['nullable', 'string', 'max:191'],
            'event.expiration_at_ms' => ['nullable', 'integer'],
        ]);

        $event = $validated['event'];
        $user = User::find($event['app_user_id']);

        if (! $user) {
            // 200 so RevenueCat does not retry forever on a deleted account.
            Log::info('RevenueCat event for unknown user', ['app_user_id' => $event['app_user_id']]);

            return $this->ok(null, 'Ignored.');
        }

        $type = strtoupper($event['type']);

        if (in_array($type, self::REVOKING_EVENTS, true)) {
            $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'expired', 'ends_at' => now()]);

            return $this->ok(null, 'Subscription expired.');
        }

        if (! in_array($type, self::GRANTING_EVENTS, true)) {
            return $this->ok(null, 'Event ignored.');
        }

        $plan = Plan::where('store_product_id', $event['product_id'] ?? null)->first()
            ?? Plan::where('slug', 'pro')->first();

        if (! $plan) {
            Log::error('RevenueCat event matched no plan', ['product_id' => $event['product_id'] ?? null]);

            return $this->ok(null, 'No matching plan.');
        }

        $expiresAt = isset($event['expiration_at_ms'])
            ? Carbon::createFromTimestampMs($event['expiration_at_ms'])
            : null;

        Subscription::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider_subscription_id' => $event['id'] ?? ($event['product_id'] ?? 'revenuecat'),
            ],
            [
                'plan_id' => $plan->id,
                'status' => 'active',
                'provider' => 'revenuecat',
                'started_at' => now(),
                'renews_at' => $expiresAt,
                'ends_at' => $expiresAt,
                'cancelled_at' => null,
            ]
        );

        return $this->ok(null, 'Subscription updated.');
    }
}
