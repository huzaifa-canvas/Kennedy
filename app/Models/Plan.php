<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name', 'slug', 'tagline', 'price', 'currency', 'billing_period',
        'store_product_id', 'bill_limit', 'income_source_limit', 'savings_jar_limit',
        'history_days', 'allows_smart_splits', 'allows_custom_reminders',
        'allows_advanced_recurrence', 'allows_calendar_themes', 'features',
        'is_default', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'bill_limit' => 'integer',
            'income_source_limit' => 'integer',
            'savings_jar_limit' => 'integer',
            'history_days' => 'integer',
            'allows_smart_splits' => 'boolean',
            'allows_custom_reminders' => 'boolean',
            'allows_advanced_recurrence' => 'boolean',
            'allows_calendar_themes' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return (float) $this->price === 0.0;
    }

    public static function default(): self
    {
        return static::where('is_default', true)->firstOrFail();
    }
}
