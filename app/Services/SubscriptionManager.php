<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Keeps our subscriptions table in step with RevenueCat and runs the
 * Pro <-> Free transition whenever access actually changes.
 *
 * RevenueCat (and through it Apple / Google) owns billing: trials, renewals,
 * billing retry and grace periods, cancellation. Saverr never charges or
 * cancels anything itself; it only mirrors the entitlement.
 */
class SubscriptionManager
{
    public const STATUS_LABELS = [
        'free' => 'Free',
        'free_trial' => 'Free Trial',
        'active' => 'Active',
        'canceled' => 'Canceled',
        'expired' => 'Expired',
    ];

    public function __construct(
        private readonly RevenueCatClient $client,
        private readonly PlanDowngrade $downgrade,
    ) {
    }

    // ───────────────────────── Sources of truth ─────────────────────────

    /**
     * Pull the subscriber from RevenueCat's API and store it.
     * Returns false when the API is not configured or did not answer.
     */
    public function refreshFromRevenueCat(User $user): bool
    {
        $subscriber = $this->client->subscriber((string) $user->id);

        if (is_null($subscriber)) {
            return false;
        }

        $this->applySubscriber($user, $subscriber);

        return true;
    }

    /** @param array<string, mixed> $subscriber RevenueCat v1 "subscriber" object */
    public function applySubscriber(User $user, array $subscriber): void
    {
        $entitlement = $subscriber['entitlements'][config('services.revenuecat.entitlement')] ?? null;

        if (! $entitlement) {
            // Never had Pro through RevenueCat, or it was revoked entirely.
            $this->expireStoreSubscriptions($user);

            return;
        }

        $productId = $entitlement['product_identifier'] ?? null;
        $store = $subscriber['subscriptions'][$productId] ?? [];

        $state = [
            'product_id' => $productId,
            'store' => $this->lower($store['store'] ?? null),
            'period_type' => $this->lower($store['period_type'] ?? null),
            'environment' => ! empty($store['is_sandbox']) ? 'sandbox' : 'production',
            'purchased_at' => $this->date($store['purchase_date'] ?? $entitlement['purchase_date'] ?? null),
            'expires_at' => $this->date($entitlement['expires_date'] ?? $store['expires_date'] ?? null),
            'grace_period_ends_at' => $this->date($entitlement['grace_period_expires_date'] ?? $store['grace_period_expires_date'] ?? null),
            'billing_issue_at' => $this->date($store['billing_issues_detected_at'] ?? null),
            'unsubscribed_at' => $this->date($store['unsubscribe_detected_at'] ?? null),
            'refunded_at' => $this->date($store['refunded_at'] ?? null),
            'management_url' => $subscriber['management_url'] ?? null,
            'event_at' => null,
        ];

        $this->store($user, $state);
    }

    /**
     * Fallback when the REST API is unavailable: apply one webhook event on
     * top of what we already know.
     *
     * @param array<string, mixed> $event
     */
    public function applyEvent(User $user, array $event): void
    {
        $type = strtoupper((string) ($event['type'] ?? ''));
        $productId = $event['product_id'] ?? null;
        $eventAt = $this->ms($event['event_timestamp_ms'] ?? null) ?? now();

        $existing = $this->liveStoreRow($user, $productId);

        // A newer event has already been applied to this row.
        if ($existing?->last_event_at && $existing->last_event_at->gt($eventAt)) {
            return;
        }

        $state = [
            'product_id' => $productId,
            'store' => $this->lower($event['store'] ?? null) ?? $existing?->store,
            'period_type' => $this->lower($event['period_type'] ?? null) ?? $existing?->period_type,
            'environment' => $this->lower($event['environment'] ?? null) ?? $existing?->environment,
            'purchased_at' => $this->ms($event['purchased_at_ms'] ?? null),
            'expires_at' => $this->ms($event['expiration_at_ms'] ?? null) ?? $existing?->ends_at,
            'grace_period_ends_at' => $existing?->grace_period_ends_at,
            'billing_issue_at' => $existing?->billing_issue_detected_at,
            'unsubscribed_at' => $existing?->cancelled_at,
            'refunded_at' => null,
            'management_url' => $existing?->management_url,
            'event_at' => $eventAt,
        ];

        switch ($type) {
            case 'INITIAL_PURCHASE':
            case 'RENEWAL':
            case 'UNCANCELLATION':
            case 'NON_RENEWING_PURCHASE':
            case 'SUBSCRIPTION_EXTENDED':
                // A successful charge or a re-enabled renewal clears problems.
                $state['unsubscribed_at'] = null;
                $state['billing_issue_at'] = null;
                $state['grace_period_ends_at'] = null;
                break;

            case 'CANCELLATION':
                // Auto-renew is off; Pro continues until expiration.
                $state['unsubscribed_at'] = $eventAt;
                break;

            case 'BILLING_ISSUE':
                // Store is retrying the charge; keep Pro through any grace period.
                $state['billing_issue_at'] = $eventAt;
                $state['grace_period_ends_at'] = $this->ms($event['grace_period_expiration_at_ms'] ?? null);
                break;

            case 'EXPIRATION':
                $state['expires_at'] = $state['expires_at'] && $state['expires_at']->isPast() ? $state['expires_at'] : $eventAt;
                $state['grace_period_ends_at'] = null;
                break;

            default:
                return;
        }

        $this->store($user, $state);
    }

    // ───────────────────────── Writing state ────────────────────────────

    /**
     * Upsert the store subscription described by $state and settle the
     * user's plan afterwards.
     *
     * @param array<string, mixed> $state
     */
    protected function store(User $user, array $state): void
    {
        $wasPro = $this->hadPro($user);

        DB::transaction(function () use ($user, $state) {
            $plan = $this->planFor($state['product_id']);

            if (! $plan) {
                return;
            }

            $now = now();
            $expiresAt = $state['expires_at'];
            $graceEnds = $state['grace_period_ends_at'];

            $hasAccess = ! $state['refunded_at']
                && (is_null($expiresAt) || $expiresAt->gt($now) || ($graceEnds && $graceEnds->gt($now)));

            $isTrial = $state['period_type'] === 'trial';

            $status = match (true) {
                ! $hasAccess => 'expired',
                (bool) $state['unsubscribed_at'] => 'cancelled',
                $isTrial => 'trialing',
                default => 'active',
            };

            $row = $this->liveStoreRow($user, $state['product_id']);

            // Nothing live to end, and nothing to grant.
            if (! $row && ! $hasAccess) {
                return;
            }

            $row ??= new Subscription(['user_id' => $user->id, 'provider' => 'revenuecat']);

            $willRenew = $hasAccess && ! $state['unsubscribed_at'] && $plan->billing_period !== 'lifetime';

            $row->fill([
                'plan_id' => $plan->id,
                'status' => $status,
                'store' => $state['store'],
                'provider_subscription_id' => $row->provider_subscription_id
                    ?? 'rc:' . $user->id . ':' . $state['product_id'] . ':' . $now->timestamp,
                'product_id' => $state['product_id'],
                'period_type' => $state['period_type'],
                'environment' => $state['environment'],
                'will_renew' => $willRenew,
                'started_at' => $row->started_at ?? $state['purchased_at'] ?? $now,
                'trial_started_at' => $isTrial ? ($row->trial_started_at ?? $state['purchased_at'] ?? $now) : $row->trial_started_at,
                'trial_ends_at' => $isTrial ? $expiresAt : $row->trial_ends_at,
                'renews_at' => $willRenew ? $expiresAt : null,
                'ends_at' => $hasAccess || ($expiresAt && $expiresAt->lt($now)) ? $expiresAt : $now,
                'cancelled_at' => $state['unsubscribed_at'],
                'billing_issue_detected_at' => $hasAccess ? $state['billing_issue_at'] : null,
                'grace_period_ends_at' => $hasAccess ? $graceEnds : null,
                'management_url' => $state['management_url'],
                'last_event_at' => $state['event_at'] ?? $row->last_event_at,
            ])->save();

            // One live store subscription per user: a product change
            // (monthly <-> yearly) supersedes the old product's row.
            $user->subscriptions()
                ->where('provider', 'revenuecat')
                ->whereKeyNot($row->id)
                ->whereIn('status', Subscription::LIVE_STATUSES)
                ->update(['status' => 'expired', 'will_renew' => false, 'ends_at' => $now, 'updated_at' => $now]);
        });

        $this->settle($user, $wasPro);
    }

    /** Expire every live RevenueCat row for the user (entitlement gone). */
    public function expireStoreSubscriptions(User $user): void
    {
        $wasPro = $this->hadPro($user);

        $user->subscriptions()
            ->where('provider', 'revenuecat')
            ->whereIn('status', Subscription::LIVE_STATUSES)
            ->update(['status' => 'expired', 'will_renew' => false, 'renews_at' => null, 'updated_at' => now()]);

        $this->settle($user, $wasPro);
    }

    /** End one subscription now (cron for manual grants, admin revoke). */
    public function expire(Subscription $subscription): void
    {
        $user = $subscription->user;

        if (! $user) {
            $subscription->update(['status' => 'expired']);

            return;
        }

        $wasPro = $this->hadPro($user);
        $now = now();

        $subscription->update([
            'status' => 'expired',
            'will_renew' => false,
            'renews_at' => null,
            'ends_at' => $subscription->ends_at && $subscription->ends_at->lt($now) ? $subscription->ends_at : $now,
            'grace_period_ends_at' => null,
        ]);

        $this->settle($user, $wasPro);
    }

    /**
     * Run the plan transition if access changed: losing Pro archives over-limit
     * data; being Pro means Free limits no longer need a selection.
     */
    public function settle(User $user, bool $wasPro): void
    {
        $isPro = $this->freshIsPro($user);

        if ($wasPro && ! $isPro) {
            $this->downgrade->downgrade($user);
            Log::info('User downgraded to Free', ['user_id' => $user->id]);

            return;
        }

        if ($isPro && $user->plan_selection_required_at) {
            $user->forceFill(['plan_selection_required_at' => null])->save();
        }
    }

    // ───────────────────────── Reading state ────────────────────────────

    /**
     * Everything the Profile / Settings screen shows about the subscription.
     *
     * @return array<string, mixed>
     */
    public function summary(User $user): array
    {
        $active = $user->activeSubscription()->with('plan')->first();
        $latest = $active ?? $user->latestSubscription()->with('plan')->first();
        $plan = $active?->plan ?? Plan::default();

        $status = $active ? $active->displayStatus() : ($latest ? 'expired' : 'free');
        $showTrial = $latest?->trial_started_at && in_array($status, ['free_trial', 'canceled'], true);

        return [
            'status' => $status,
            'status_label' => self::STATUS_LABELS[$status],
            'is_pro' => (bool) $active,
            'plan_slug' => $plan->slug,
            'plan_name' => $plan->name,
            'price' => $active ? (float) $plan->price : null,
            'currency' => $plan->currency,
            'billing_period' => $active ? $plan->billing_period : null,
            'store' => $active?->store ?? $latest?->store,
            'trial_started_at' => $showTrial ? $latest->trial_started_at->toIso8601String() : null,
            'trial_ends_at' => $showTrial ? $latest->trial_ends_at?->toIso8601String() : null,
            'next_billing_date' => $active && $active->will_renew ? $active->renews_at?->toIso8601String() : null,
            'auto_renew' => $active ? (bool) $active->will_renew : false,
            'access_ends_at' => $active && $status === 'canceled' ? $active->accessEndsAt()?->toIso8601String() : null,
            'billing_issue' => $active ? $active->inBillingGrace() : false,
            'grace_period_ends_at' => $active?->grace_period_ends_at?->toIso8601String(),
            'expired_at' => ! $active && $latest ? $latest->ends_at?->toIso8601String() : null,
            'manage_subscription_url' => ($active ?? $latest)?->managementUrl(),
            'can_upgrade' => ! $active,
            'requires_plan_selection' => $user->requiresPlanSelection(),
            'has_restorable_archive' => $active ? $user->hasPlanArchive() : false,
        ];
    }

    // ───────────────────────── Helpers ──────────────────────────────────

    /**
     * Pro as far as our records go, before a change is applied. A live row
     * whose dates have lapsed still counts: it has not been processed yet,
     * so its data was never moved to Free limits.
     */
    private function hadPro(User $user): bool
    {
        return $this->freshIsPro($user)
            || $user->subscriptions()->whereIn('status', Subscription::LIVE_STATUSES)->exists();
    }

    private function freshIsPro(User $user): bool
    {
        $user->unsetRelation('activeSubscription');

        return $user->isPro();
    }

    private function liveStoreRow(User $user, ?string $productId): ?Subscription
    {
        return $user->subscriptions()
            ->where('provider', 'revenuecat')
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->whereIn('status', Subscription::LIVE_STATUSES)
            ->latest('id')
            ->first();
    }

    private function planFor(?string $productId): ?Plan
    {
        $plan = Plan::forStoreProduct($productId);

        if ($plan) {
            return $plan;
        }

        // Promotional grants and unmapped products still mean Pro.
        Log::warning('RevenueCat product matched no plan; using Pro Monthly', ['product_id' => $productId]);

        return Plan::where('slug', 'pro_monthly')->first();
    }

    private function date(?string $value): ?Carbon
    {
        return $value ? Carbon::parse($value) : null;
    }

    private function ms(mixed $value): ?Carbon
    {
        return is_numeric($value) ? Carbon::createFromTimestampMs((int) $value) : null;
    }

    private function lower(?string $value): ?string
    {
        return $value ? strtolower($value) : null;
    }
}
