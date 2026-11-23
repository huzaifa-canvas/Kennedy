<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One store subscription (keyed by RevenueCat's original_transaction_id).
 *
 * Status lifecycle:
 *   trialing  - inside the 14-day free trial
 *   active    - paid and current
 *   cancelled - auto-renew turned off; Pro is kept until access ends
 *   expired   - access is over; the user is on Free
 *
 * Pro access is decided by dates, not by status alone: a cancelled trial or
 * paid term keeps Pro until it runs out, and a billing issue keeps Pro until
 * the store's grace period runs out.
 */
class Subscription extends Model
{
    public const STATUSES = ['trialing', 'active', 'cancelled', 'expired'];

    /** Statuses that may still carry Pro access, subject to the dates. */
    public const LIVE_STATUSES = ['trialing', 'active', 'cancelled'];

    protected $fillable = [
        'user_id', 'plan_id', 'status', 'provider', 'store', 'provider_subscription_id',
        'product_id', 'period_type', 'environment', 'will_renew',
        'started_at', 'trial_started_at', 'trial_ends_at', 'renews_at', 'ends_at',
        'cancelled_at', 'billing_issue_detected_at', 'grace_period_ends_at',
        'management_url', 'last_event_at',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'status' => 'active',
        'provider' => 'revenuecat',
        'will_renew' => true,
    ];

    protected function casts(): array
    {
        return [
            'will_renew' => 'boolean',
            'started_at' => 'datetime',
            'trial_started_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'billing_issue_detected_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Subscriptions that currently grant Pro. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES)
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now())
                    ->orWhere('grace_period_ends_at', '>', now());
            });
    }

    /** Live rows whose access window (including grace) has closed. */
    public function scopeAccessEndedBefore(Builder $query, Carbon $moment): Builder
    {
        return $query->whereIn('status', self::LIVE_STATUSES)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $moment)
            ->where(function (Builder $q) use ($moment) {
                $q->whereNull('grace_period_ends_at')->orWhere('grace_period_ends_at', '<', $moment);
            });
    }

    /** Does this subscription grant Pro right now? */
    public function isActive(): bool
    {
        if (! in_array($this->status, self::LIVE_STATUSES, true)) {
            return false;
        }

        $accessEnds = $this->accessEndsAt();

        return is_null($accessEnds) || $accessEnds->isFuture();
    }

    /** Last moment of Pro access, grace period included. Null = open-ended. */
    public function accessEndsAt(): ?Carbon
    {
        if (is_null($this->ends_at)) {
            return null;
        }

        if ($this->grace_period_ends_at && $this->grace_period_ends_at->gt($this->ends_at)) {
            return $this->grace_period_ends_at;
        }

        return $this->ends_at;
    }

    public function isTrial(): bool
    {
        return $this->status === 'trialing'
            || ($this->period_type === 'trial' && $this->isActive());
    }

    public function inBillingGrace(): bool
    {
        return $this->billing_issue_detected_at !== null && $this->isActive();
    }

    /**
     * What the app shows: free, free_trial, active, canceled or expired.
     * "canceled" means auto-renew is off but Pro has not ended yet.
     */
    public function displayStatus(): string
    {
        if (! $this->isActive()) {
            return 'expired';
        }

        if (! $this->will_renew || $this->status === 'cancelled') {
            return 'canceled';
        }

        return $this->isTrial() ? 'free_trial' : 'active';
    }

    /** Where the user manages or cancels the subscription. Saverr never cancels it. */
    public function managementUrl(): ?string
    {
        if ($this->management_url) {
            return $this->management_url;
        }

        return match ($this->store) {
            'app_store' => 'https://apps.apple.com/account/subscriptions',
            'play_store' => $this->playStoreManagementUrl(),
            default => null,
        };
    }

    protected function playStoreManagementUrl(): string
    {
        $base = 'https://play.google.com/store/account/subscriptions';
        $package = config('services.revenuecat.android_package');

        if (! $package || ! $this->product_id) {
            return $base;
        }

        // RevenueCat reports Play products as "subscriptionId:basePlanId".
        $sku = explode(':', $this->product_id)[0];

        return $base . '?' . http_build_query(['sku' => $sku, 'package' => $package]);
    }
}
