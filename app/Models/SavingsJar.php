<?php

namespace App\Models;

use App\Models\Concerns\ArchivableByPlan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsJar extends Model
{
    use ArchivableByPlan, SoftDeletes;

    protected $fillable = [
        'user_id', 'name', 'target_amount', 'saved_amount', 'icon', 'color',
        'completed_at', 'is_archived',
        'archived_at', 'archived_reason',
    ];

    protected $appends = ['progress_percent'];

    /** Column defaults, so a freshly created model matches what the DB stores. */
    protected $attributes = [
        'saved_amount' => 0,
        'icon' => 'tabler-target',
        'color' => '#F97316',
        'is_archived' => false,
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'saved_amount' => 'decimal:2',
            'completed_at' => 'datetime',
            'is_archived' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_archived', false);
    }

    public function getProgressPercentAttribute(): int
    {
        $target = (float) $this->target_amount;

        if ($target <= 0) {
            return 0;
        }

        return (int) min(100, floor(((float) $this->saved_amount / $target) * 100));
    }

    protected function activeAttributes(): array
    {
        return ['is_archived' => false];
    }

    protected function inactiveAttributes(): array
    {
        return ['is_archived' => true];
    }
}
