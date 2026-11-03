<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Turns a bill definition into the dated occurrences that drive the
 * calendar, the bill list totals and the set-aside calculation.
 */
class BillScheduler
{
    /** How far ahead occurrences are materialised. */
    public const HORIZON_MONTHS = 12;

    /**
     * Create any missing occurrences for a bill up to the horizon.
     * Existing rows are never touched, so paid history is safe.
     */
    public function sync(Bill $bill, ?Carbon $horizon = null): void
    {
        if (! $bill->is_active) {
            return;
        }

        $horizon ??= Carbon::today()->addMonths(self::HORIZON_MONTHS);
        $cursor = $bill->due_date->copy();

        $existing = $bill->occurrences()
            ->pluck('due_date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->flip();

        $rows = [];
        $guard = 0;

        while ($cursor->lte($horizon) && $guard++ < 500) {
            if (! $existing->has($cursor->toDateString())) {
                $rows[] = [
                    'bill_id' => $bill->id,
                    'user_id' => $bill->user_id,
                    'amount' => $bill->amount,
                    'due_date' => $cursor->toDateString(),
                    'status' => $cursor->isBefore(Carbon::today()) ? 'overdue' : 'upcoming',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $next = $bill->advance($cursor);

            if (! $next) {
                break;
            }

            $cursor = $next;
        }

        if ($rows !== []) {
            BillOccurrence::insertOrIgnore($rows);
        }

        $this->refreshNextDueDate($bill);
    }

    /**
     * Re-generate future occurrences after a bill is edited.
     * Paid occurrences and anything already past stay untouched.
     */
    public function resync(Bill $bill): void
    {
        $bill->occurrences()
            ->where('status', '!=', 'paid')
            ->whereDate('due_date', '>=', Carbon::today())
            ->delete();

        $this->sync($bill);
    }

    /** Flip anything still unpaid past its due date to overdue. */
    public function markOverdue(?User $user = null): int
    {
        return BillOccurrence::query()
            ->when($user, fn ($query) => $query->where('user_id', $user->id))
            ->where('status', 'upcoming')
            ->whereDate('due_date', '<', Carbon::today())
            ->update(['status' => 'overdue', 'updated_at' => now()]);
    }

    public function refreshNextDueDate(Bill $bill): void
    {
        $next = $bill->occurrences()
            ->unpaid()
            ->whereDate('due_date', '>=', Carbon::today())
            ->orderBy('due_date')
            ->value('due_date');

        $bill->forceFill(['next_due_date' => $next])->saveQuietly();
    }
}
