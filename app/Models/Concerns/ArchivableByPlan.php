<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Items (bills, income sources, savings jars) that a Free-plan downgrade can
 * archive. A downgrade-archived item is kept, but every planning feature
 * ignores it until the user restores it after upgrading back to Pro.
 *
 * Each model says which boolean column means "in use" via activeColumn():
 * bills and income sources use is_active, jars use is_archived (inverted).
 */
trait ArchivableByPlan
{
    public const ARCHIVED_BY_PLAN = 'plan_downgrade';

    public function initializeArchivableByPlan(): void
    {
        $this->mergeCasts(['archived_at' => 'datetime']);
    }

    /** Archived because the account dropped to Free (restorable). */
    public function scopeArchivedByPlan(Builder $query): Builder
    {
        return $query->where('archived_reason', self::ARCHIVED_BY_PLAN);
    }

    public function isArchivedByPlan(): bool
    {
        return $this->archived_reason === self::ARCHIVED_BY_PLAN;
    }

    /** Take the item out of every active calculation, keeping its data. */
    public function archiveForPlan(): void
    {
        $this->forceFill($this->inactiveAttributes() + [
            'archived_at' => now(),
            'archived_reason' => self::ARCHIVED_BY_PLAN,
        ])->save();
    }

    /** Put the item back into use and forget why it was archived. */
    public function reactivate(): void
    {
        $this->forceFill($this->activeAttributes() + [
            'archived_at' => null,
            'archived_reason' => null,
        ])->save();
    }

    /** @return array<string, bool> */
    abstract protected function activeAttributes(): array;

    /** @return array<string, bool> */
    abstract protected function inactiveAttributes(): array;
}
