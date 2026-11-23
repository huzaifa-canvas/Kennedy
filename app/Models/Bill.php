<?php

namespace App\Models;

use App\Models\Concerns\ArchivableByPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Bill extends Model
{
    use ArchivableByPlan, HasFactory, SoftDeletes;

    public const RECURRENCES = ['one_time', 'weekly', 'biweekly', 'monthly'];

    protected $fillable = [
        'user_id', 'bill_category_id', 'name', 'amount', 'due_date', 'recurrence',
        'next_due_date', 'color', 'reminder_days_before', 'notes', 'is_active',
        'archived_at', 'archived_reason',
    ];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'recurrence' => 'monthly',
        'reminder_days_before' => 2,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'next_due_date' => 'date',
            'reminder_days_before' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(BillCategory::class, 'bill_category_id');
    }

    public function occurrences(): HasMany
    {
        return $this->hasMany(BillOccurrence::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function isRecurring(): bool
    {
        return $this->recurrence !== 'one_time';
    }

    /** Advance a due date by one recurrence step. */
    public function advance(\Illuminate\Support\Carbon $from): ?\Illuminate\Support\Carbon
    {
        return match ($this->recurrence) {
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'monthly' => $from->copy()->addMonthNoOverflow(),
            default => null,
        };
    }

    protected function activeAttributes(): array
    {
        return ['is_active' => true];
    }

    protected function inactiveAttributes(): array
    {
        return ['is_active' => false];
    }
}
