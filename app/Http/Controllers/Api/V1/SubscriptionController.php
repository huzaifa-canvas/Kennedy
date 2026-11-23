<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PlanSelectionRequest;
use App\Http\Resources\PlanResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Bill;
use App\Models\IncomeSource;
use App\Models\Plan;
use App\Models\SavingsJar;
use App\Services\PlanDowngrade;
use App\Services\RevenueCatClient;
use App\Services\SubscriptionManager;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Go Premium screen, subscription status, and the Free-plan selection /
 * restore flow.
 *
 * Purchases, trials and cancellations all happen in the App Store / Google
 * Play through RevenueCat. Saverr never cancels a subscription: the app
 * opens `manage_subscription_url` and the user manages it with the store.
 */
class SubscriptionController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly PlanDowngrade $downgrade,
        private readonly RevenueCatClient $revenueCat,
    ) {
    }

    /** Paywall + current subscription status. */
    public function index(Request $request): JsonResponse
    {
        return $this->ok($this->payload($request->user()));
    }

    /**
     * Called by the app right after a RevenueCat purchase or restore so the
     * UI updates without waiting for the webhook. The entitlement is read
     * from RevenueCat's API; nothing the app sends is trusted.
     */
    public function sync(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $this->revenueCat->isConfigured()) {
            return $this->fail('Subscription verification is not configured on the server.', 503);
        }

        if (! $this->subscriptions->refreshFromRevenueCat($user)) {
            return $this->fail('Could not reach the subscription service. Please try again.', 502);
        }

        return $this->ok($this->payload($user->fresh()), 'Subscription refreshed.');
    }

    /**
     * What the user can keep active on Free: every bill / income / jar that
     * competes for a slot, with the current choice marked.
     */
    public function selection(Request $request): JsonResponse
    {
        $user = $request->user();
        $candidates = $this->downgrade->candidates($user);

        return $this->ok([
            'requires_plan_selection' => $user->requiresPlanSelection(),
            'is_pro' => $user->isPro(),
            'limits' => $this->downgrade->freeLimits(),
            'bills' => $candidates['bills']->map(fn (Bill $bill) => [
                'id' => $bill->id,
                'name' => $bill->name,
                'amount' => (float) $bill->amount,
                'recurrence' => $bill->recurrence,
                'next_due_date' => $bill->next_due_date?->toDateString(),
                'selected' => (bool) $bill->is_active,
            ])->values(),
            'income_sources' => $candidates['income_sources']->map(fn (IncomeSource $source) => [
                'id' => $source->id,
                'name' => $source->name,
                'amount' => (float) $source->amount,
                'pay_frequency' => $source->pay_frequency,
                'is_primary' => (bool) $source->is_primary,
                'selected' => (bool) $source->is_active,
            ])->values(),
            'savings_jars' => $candidates['savings_jars']->map(fn (SavingsJar $jar) => [
                'id' => $jar->id,
                'name' => $jar->name,
                'saved_amount' => (float) $jar->saved_amount,
                'target_amount' => (float) $jar->target_amount,
                'selected' => ! $jar->is_archived,
            ])->values(),
        ]);
    }

    /** Save which items stay active on Free; the rest are archived, not deleted. */
    public function saveSelection(PlanSelectionRequest $request): JsonResponse
    {
        $user = $request->user();

        if ($user->isPro()) {
            return $this->fail('Pro has no limits, so there is nothing to choose.', 409);
        }

        $this->downgrade->applySelection($user, $request->validated());

        return $this->ok($this->payload($user->fresh()), 'Your Free plan selection has been saved.');
    }

    /** After upgrading again, bring back everything the downgrade archived. */
    public function restore(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->isPro()) {
            throw new PlanLimitException('Upgrade to Saverr Pro to restore your archived data.', feature: 'restore');
        }

        $restored = $this->downgrade->restore($user);

        return $this->ok([
            'restored' => $restored,
            'subscription' => $this->subscriptions->summary($user->fresh()),
        ], 'Your previous Pro data has been restored.');
    }

    /** @return array<string, mixed> */
    private function payload($user): array
    {
        $active = $user->activeSubscription()->with('plan')->first();

        return [
            'status' => $this->subscriptions->summary($user),
            'current_plan' => new PlanResource($user->plan()),
            'subscription' => $active ? new SubscriptionResource($active) : null,
            'plans' => PlanResource::collection(
                Plan::where('is_active', true)->orderBy('sort_order')->get()
            ),
            'usage' => [
                'bills' => $user->bills()->where('is_active', true)->count(),
                'income_sources' => $user->incomeSources()->where('is_active', true)->count(),
                'savings_jars' => $user->savingsJars()->where('is_archived', false)->count(),
            ],
            'archived' => [
                'bills' => $user->bills()->archivedByPlan()->count(),
                'income_sources' => $user->incomeSources()->archivedByPlan()->count(),
                'savings_jars' => $user->savingsJars()->archivedByPlan()->count(),
            ],
        ];
    }
}
