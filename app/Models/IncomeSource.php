<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class IncomeSource extends Model
{
    use SoftDeletes;

    public const FREQUENCIES = ['weekly', 'biweekly', 'semimonthly', 'monthly', 'varies'];

    protected $fillable = [
        'user_id', 'name', 'amount', 'pay_frequency', 'next_payday', 'is_primary', 'is_active',
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

    /** Number of paydays this source produces in a calendar month. */
    public function paydaysPerMonth(): float
    {
        return match ($this->pay_frequency) {
            'weekly' => 52 / 12,
            'biweekly' => 26 / 12,
            'semimonthly' => 2,
            default => 1,
        };
    }

    public function advancePayday(Carbon $from): Carbon
    {
        return match ($this->pay_frequency) {
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'semimonthly' => $from->day <= 15
                ? $from->copy()->day(min(28, $from->daysInMonth))
                : $from->copy()->addMonthNoOverflow()->day(15),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    public function monthlyEquivalent(): float
    {
        return round((float) $this->amount * $this->paydaysPerMonth(), 2);
    }
}
