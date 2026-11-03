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

    public const PAY_FREQUENCIES = ['weekly', 'biweekly', 'semimonthly', 'monthly', 'varies'];

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
}
