<?php

namespace App\Services;

use App\Models\BillOccurrence;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The heart of the app: how much a user must hold back from their next
 * paycheck so every upcoming bill is covered.
 *
 * Free plan  - cover every bill falling due before the paycheck after next.
 * Pro plan   - smart splits: each bill is spread evenly over the paychecks
 *              that land before it is due, so no single paycheck is crushed.
 */
class SetAsideCalculator
{
    /** How far ahead smart splits look. */
    public const SPLIT_HORIZON_DAYS = 120;

    /** Paydays to project when the user has no explicit schedule. */
    public const PROJECTED_PAYDAYS = 12;

    /**
     * Upcoming paydays, earliest first, starting from today.
     *
     * @return Collection<int, Carbon>
     */
    public function upcomingPaydays(User $user, int $limit = self::PROJECTED_PAYDAYS): Collection
    {
        $sources = $user->relationLoaded('incomeSources')
            ? $user->incomeSources->where('is_active', true)
            : $user->incomeSources()->active()->get();

        $today = Carbon::today();
        $dates = collect();

        foreach ($sources as $source) {
            if (! $source->next_payday) {
                continue;
            }

            $cursor = $source->next_payday->copy();

            // Roll a stale payday forward so projections always start in the future.
            $guard = 0;
            while ($cursor->lt($today) && $source->pay_frequency !== 'varies' && $guard++ < 500) {
                $cursor = $source->advancePayday($cursor);
            }

            for ($i = 0; $i < $limit; $i++) {
                $dates->push($cursor->copy());

                if ($source->pay_frequency === 'varies') {
                    break;
                }

                $cursor = $source->advancePayday($cursor);
            }
        }

        // Fall back to the schedule captured during onboarding.
        if ($dates->isEmpty() && $user->next_payday) {
            $cursor = $user->next_payday->copy();
            $frequency = $user->pay_frequency ?? 'monthly';

            $guard = 0;
            while ($cursor->lt($today) && $frequency !== 'varies' && $guard++ < 500) {
                $cursor = $this->advance($cursor, $frequency);
            }

            for ($i = 0; $i < $limit; $i++) {
                $dates->push($cursor->copy());

                if ($frequency === 'varies') {
                    break;
                }

                $cursor = $this->advance($cursor, $frequency);
            }
        }

        return $dates
            ->filter(fn (Carbon $date) => $date->gte($today))
            ->unique(fn (Carbon $date) => $date->toDateString())
            ->sort()
            ->values()
            ->take($limit);
    }

    public function nextPayday(User $user): ?Carbon
    {
        return $this->upcomingPaydays($user, 2)->first();
    }

    /**
     * The headline dashboard figure.
     *
     * @return array{amount: float, next_payday: ?string, covers_until: ?string, breakdown: array<int, array<string, mixed>>}
     */
    public function setAside(User $user): array
    {
        $paydays = $this->upcomingPaydays($user);
        $nextPayday = $paydays->first();
        $followingPayday = $paydays->get(1);
        $smartSplits = $user->plan()->allows_smart_splits;

        $horizonEnd = $smartSplits
            ? Carbon::today()->addDays(self::SPLIT_HORIZON_DAYS)
            : ($followingPayday?->copy() ?? Carbon::today()->addMonthNoOverflow());

        $occurrences = $this->unpaidOccurrences($user, Carbon::today(), $horizonEnd);

        $total = 0.0;
        $breakdown = [];

        foreach ($occurrences as $occurrence) {
            $amount = (float) $occurrence->amount;

            if ($smartSplits) {
                $paychecksBefore = $paydays
                    ->filter(fn (Carbon $date) => $date->lte($occurrence->due_date))
                    ->count();

                $share = $paychecksBefore > 0
                    ? round($amount / $paychecksBefore, 2)
                    : $amount;
            } else {
                $share = $amount;
            }

            $total += $share;

            $breakdown[] = [
                'bill_id' => $occurrence->bill_id,
                'occurrence_id' => $occurrence->id,
                'name' => $occurrence->bill?->name,
                'due_date' => $occurrence->due_date->toDateString(),
                'full_amount' => round($amount, 2),
                'set_aside_amount' => round($share, 2),
            ];
        }

        return [
            'amount' => round($total, 2),
            'next_payday' => $nextPayday?->toDateString(),
            'covers_until' => $horizonEnd->toDateString(),
            'smart_splits' => $smartSplits,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Spendable money per week and per day between now and the next payday.
     *
     * @return array{leftover: float, days_until_payday: int, daily: float, weekly: float, view: string}
     */
    public function allowance(User $user, ?float $setAside = null): array
    {
        $setAside ??= $this->setAside($user)['amount'];
        $nextPayday = $this->nextPayday($user);

        $days = $nextPayday
            ? max(1, Carbon::today()->diffInDays($nextPayday, false))
            : 30;

        $expectedPay = $this->expectedPaycheckAmount($user);
        $leftover = max(0, round($expectedPay - $setAside, 2));

        return [
            'leftover' => $leftover,
            'days_until_payday' => (int) $days,
            'daily' => round($leftover / $days, 2),
            'weekly' => round(($leftover / $days) * 7, 2),
            'view' => $user->allowance_view ?? 'weekly',
        ];
    }

    /**
     * Income / bills / remaining for a calendar month.
     *
     * @return array{income: float, bills: float, remaining: float, paid: float, unpaid: float}
     */
    public function monthlyOverview(User $user, ?Carbon $month = null): array
    {
        $month ??= Carbon::today();
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $sources = $user->relationLoaded('incomeSources')
            ? $user->incomeSources->where('is_active', true)
            : $user->incomeSources()->active()->get();

        $income = $sources->sum(fn ($source) => $source->monthlyEquivalent());

        if ($income <= 0) {
            $income = (float) ($user->estimated_paycheck_amount ?? 0) * $this->paydaysPerMonth($user->pay_frequency);
        }

        $occurrences = BillOccurrence::query()
            ->where('user_id', $user->id)
            ->forActiveBills()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'skipped')
            ->get(['amount', 'status', 'paid_amount']);

        $bills = (float) $occurrences->sum('amount');
        $paid = (float) $occurrences->where('status', 'paid')
            ->sum(fn ($row) => $row->paid_amount ?? $row->amount);

        return [
            'income' => round($income, 2),
            'bills' => round($bills, 2),
            'remaining' => round($income - $bills, 2),
            'paid' => round($paid, 2),
            'unpaid' => round($bills - $paid, 2),
        ];
    }

    public function expectedPaycheckAmount(User $user): float
    {
        $primary = $user->primaryIncome();

        if ($primary && (float) $primary->amount > 0) {
            return (float) $primary->amount;
        }

        if ($user->estimated_paycheck_amount) {
            return (float) $user->estimated_paycheck_amount;
        }

        return (float) ($user->paychecks()->latest('received_on')->value('amount') ?? 0);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, BillOccurrence>
     */
    protected function unpaidOccurrences(User $user, Carbon $from, Carbon $to)
    {
        return BillOccurrence::query()
            ->with('bill:id,name,bill_category_id,color')
            ->where('user_id', $user->id)
            ->forActiveBills()
            ->unpaid()
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('due_date')
            ->get();
    }

    protected function advance(Carbon $from, string $frequency): Carbon
    {
        return match ($frequency) {
            'weekly' => $from->copy()->addWeek(),
            'biweekly' => $from->copy()->addWeeks(2),
            'semimonthly' => $from->day <= 15
                ? $from->copy()->day(min(28, $from->daysInMonth))
                : $from->copy()->addMonthNoOverflow()->day(15),
            default => $from->copy()->addMonthNoOverflow(),
        };
    }

    protected function paydaysPerMonth(?string $frequency): float
    {
        return match ($frequency) {
            'weekly' => 52 / 12,
            'biweekly' => 26 / 12,
            'semimonthly' => 2,
            default => 1,
        };
    }
}
