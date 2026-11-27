<?php

namespace App\Models;

use App\Models\Concerns\ArchivableByPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class IncomeSource extends Model
{
    use ArchivableByPlan, SoftDeletes;

    public const FREQUENCIES = ['one_time', 'weekly', 'biweekly', 'monthly'];

    public const FREQUENCY_LABELS = [
        'one_time' => 'One Time',
        'weekly' => 'Weekly',
        'biweekly' => 'Every 2 Weeks',
        'monthly' => 'Monthly',
    ];

    protected $fillable = [
        'user_id', 'name', 'amount', 'pay_frequency', 'next_payday', 'is_primary', 'is_active',
        'archived_at', 'archived_reason',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'amount' => 0,
        'pay_frequency' => 'biweekly',
        'is_primary' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'next_payday' => 'date',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paychecks(): HasMany
    {
        return $this->hasMany(Paycheck::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public static function frequencyLabel(?string $frequency): ?string
    {
        return $frequency ? (self::FREQUENCY_LABELS[$frequency] ?? ucfirst($frequency)) : null;
    }

    /** A one-time payment is paid once and never rolls forward. */
    public function isRecurring(): bool
    {
        return self::frequencyRepeats($this->pay_frequency);
    }

    public static function frequencyRepeats(?string $frequency): bool
    {
        return $frequency !== 'one_time';
    }

    /** Number of paydays this source produces in a calendar month (0 for one-time income). */
    public function paydaysPerMonth(): float
    {
        return self::paydaysPerMonthFor($this->pay_frequency);
    }

    public static function paydaysPerMonthFor(?string $frequency): float
    {
        return match ($frequency) {
            'one_time' => 0,
            'weekly' => 52 / 12,
            'biweekly' => 26 / 12,
            default => 1,
        };
    }

    public function advancePayday(Carbon $from): Carbon
    {
        return self::advanceFor($from, $this->pay_frequency);
    }

    /** The payday after $from; a one-time payment has no next payday, so it stays put. */
    public static function advanceFor(Carbon $from, ?string $frequency): Carbon
    {
        return match ($frequency) {
            'one_time' => $from->copy(),
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    /** Recurring income per month; one-time income is counted only in its own month (see SetAsideCalculator). */
    public function monthlyEquivalent(): float
    {
        return round((float) $this->amount * $this->paydaysPerMonth(), 2);
    }

    protected function activeAttributes(): array
    {
        return ['is_active' => true];
    }

    protected function inactiveAttributes(): array
    {
        return ['is_active' => false, 'is_primary' => false];
    }
}
