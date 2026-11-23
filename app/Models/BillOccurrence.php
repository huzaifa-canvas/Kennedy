<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillOccurrence extends Model
{
    protected $fillable = [
        'bill_id', 'user_id', 'amount', 'due_date', 'status', 'paid_amount', 'paid_at',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'status' => 'upcoming',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', ['upcoming', 'overdue']);
    }

    /**
     * Only occurrences of bills that are in use. Every planning figure
     * (set-aside, My Plan, calendar, overview, upcoming) must go through
     * this, so paused, deleted or plan-archived bills never leak into totals.
     */
    public function scopeForActiveBills(Builder $query): Builder
    {
        return $query->whereHas('bill', fn (Builder $bill) => $bill->where('is_active', true));
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('due_date', [$from, $to]);
    }
}
