<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SyncSubscriptionRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The Paywall / Go Premium screen and the RevenueCat handshake.
 */
class SubscriptionController extends Controller
{
    use ApiResponses;

    /** Paywall content: both plans plus what the user is on today. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->ok([
            'current_plan' => new PlanResource($user->plan()),
            'subscription' => $user->activeSubscription
                ? new SubscriptionResource($user->activeSubscription->load('plan'))
                : null,
            'plans' => PlanResource::collection(
                Plan::where('is_active', true)->orderBy('sort_order')->get()
            ),
            'usage' => [
                'bills' => $user->bills()->where('is_active', true)->count(),
                'income_sources' => $user->incomeSources()->where('is_active', true)->count(),
                'savings_jars' => $user->savingsJars()->where('is_archived', false)->count(),
            ],
        ]);
    }

    /**
     * Called after a RevenueCat purchase or restore.
     *
     * The receipt itself is verified by RevenueCat's webhook; this endpoint
     * only reflects the entitlement the app reports so the UI updates at
     * once, and it can never grant a term longer than the app claims.
     */
    public function sync(SyncSubscriptionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $plan = Plan::where('slug', $data['plan_slug'])->firstOrFail();

        $subscription = DB::transaction(function () use ($user, $plan, $data) {
            // One live subscription per user.
            $user->subscriptions()
                ->whereIn('status', ['active', 'trialing'])
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return Subscription::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider_subscription_id' => $data['provider_subscription_id'],
                ],
                [
                    'plan_id' => $plan->id,
                    'status' => ! empty($data['is_trial']) ? 'trialing' : 'active',
                    'provider' => 'revenuecat',
                    'started_at' => now(),
                    'renews_at' => $data['expires_at'] ?? null,
                    'ends_at' => $data['expires_at'] ?? null,
                    'cancelled_at' => null,
                ]
            );
        });

        return $this->ok([
            'subscription' => new SubscriptionResource($subscription->load('plan')),
            'plan' => new PlanResource($plan),
        ], 'Subscription activated.');
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->activeSubscription;

        if (! $subscription) {
            return $this->fail('You do not have an active subscription.', 404);
        }

        // Access runs to the end of the paid term.
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return $this->ok(
            new SubscriptionResource($subscription->fresh()->load('plan')),
            'Subscription cancelled. You keep Pro until the end of your billing period.'
        );
    }
}
