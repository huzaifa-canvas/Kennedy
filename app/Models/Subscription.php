<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'user_id', 'plan_id', 'status', 'provider', 'provider_subscription_id',
        'started_at', 'renews_at', 'ends_at', 'cancelled_at',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'status' => 'active',
        'provider' => 'revenuecat',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'renews_at' => 'datetime',
            'ends_at' => 'datetime',
            'cancelled_at' => 'datetime',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['active', 'trialing'])
            ->where(function (Builder $q) {
                $q->whereNull('ends_at')->orWhere('ends_at', '>', now());
            });
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing'], true)
            && (is_null($this->ends_at) || $this->ends_at->isFuture());
    }
}
