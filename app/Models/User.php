<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, SoftDeletes;

    public const PAY_FREQUENCIES = IncomeSource::FREQUENCIES;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'avatar',
        'role',
        'status',
        'currency',
        'timezone',
        'pay_frequency',
        'next_payday',
        'estimated_paycheck_amount',
        'allowance_view',
        'notifications_enabled',
        'onboarding_completed_at',
        'plan_selection_required_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'role' => 'user',
        'status' => 'active',
        'currency' => 'USD',
        'timezone' => 'UTC',
        'allowance_view' => 'weekly',
        'notifications_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'next_payday' => 'date',
            'estimated_paycheck_amount' => 'decimal:2',
            'notifications_enabled' => 'boolean',
            'onboarding_completed_at' => 'datetime',
            'plan_selection_required_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    // ───────────────────────────── Relations ─────────────────────────────

    public function incomeSources(): HasMany
    {
        return $this->hasMany(IncomeSource::class);
    }

    public function paychecks(): HasMany
    {
        return $this->hasMany(Paycheck::class);
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public function billOccurrences(): HasMany
    {
        return $this->hasMany(BillOccurrence::class);
    }

    public function savingsJars(): HasMany
    {
        return $this->hasMany(SavingsJar::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(UserDevice::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->active()->latestOfMany();
    }

    /** Most recent subscription of any status, for "Expired" / history display. */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    // ───────────────────────────── Scopes ─────────────────────────────

    public function scopeAdmins(Builder $query): Builder
    {
        return $query->where('role', 'admin');
    }

    public function scopeCustomers(Builder $query): Builder
    {
        return $query->where('role', 'user');
    }

    // ───────────────────────────── Helpers ─────────────────────────────

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** The plan backing this user — their subscription's plan, or the free default. */
    public function plan(): Plan
    {
        $subscription = $this->relationLoaded('activeSubscription')
            ? $this->activeSubscription
            : $this->activeSubscription()->with('plan')->first();

        return $subscription?->plan ?? Plan::default();
    }

    public function isPro(): bool
    {
        return ! $this->plan()->isFree();
    }

    /** Downgraded with more data than Free allows, and has not picked what stays yet. */
    public function requiresPlanSelection(): bool
    {
        return ! is_null($this->plan_selection_required_at);
    }

    /** Anything archived by a Free downgrade that a Pro user could bring back. */
    public function hasPlanArchive(): bool
    {
        return $this->bills()->archivedByPlan()->exists()
            || $this->incomeSources()->archivedByPlan()->exists()
            || $this->savingsJars()->archivedByPlan()->exists();
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return asset('storage/' . $this->avatar);
        }

        return asset('assets/img/avatars/1.png');
    }

    public function primaryIncome(): ?IncomeSource
    {
        return $this->incomeSources()->active()
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /**
     * Keep exactly one active primary income source and mirror its schedule
     * onto the user (payday maths reads it). When the primary was paused,
     * deleted or un-set, the next active source (oldest first) takes over.
     *
     * $exceptId is a source that must not be picked while any other active
     * source exists, e.g. the one whose `is_primary` was just turned off.
     */
    public function ensurePrimaryIncome(?int $exceptId = null): void
    {
        $active = $this->incomeSources()->active()->orderByDesc('is_primary')->orderBy('id')->get();
        $candidates = $active->where('id', '!=', $exceptId);

        if ($candidates->isEmpty()) {
            $candidates = $active;
        }

        // Already ordered: current primary first, then oldest.
        $primary = $candidates->first();

        if (! $primary) {
            return;
        }

        if (! $primary->is_primary) {
            $primary->forceFill(['is_primary' => true])->save();
        }

        $this->incomeSources()
            ->whereKeyNot($primary->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $this->forceFill([
            'pay_frequency' => $primary->pay_frequency,
            'next_payday' => $primary->next_payday,
            'estimated_paycheck_amount' => $primary->amount,
        ])->save();
    }
}
