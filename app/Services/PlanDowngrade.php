<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\IncomeSource;
use App\Models\Plan;
use App\Models\SavingsJar;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Moving an account between Pro and Free without losing anything.
 *
 *  - downgrade(): the moment Pro ends, anything over the Free limits is
 *    archived (never deleted) so Free calculations only ever see the
 *    allowed number of items. If something had to be archived, the user is
 *    flagged to choose which items stay active.
 *  - applySelection(): the user's own choice of what stays active.
 *  - restore(): after upgrading again, bring every archived item back.
 *
 * Only items archived by a downgrade are touched. Things the user paused or
 * archived on their own are left exactly as they were.
 */
class PlanDowngrade
{
    public const TYPES = ['bills', 'income_sources', 'savings_jars'];

    public function __construct(private readonly BillScheduler $scheduler)
    {
    }

    /** @return array{bills: ?int, income_sources: ?int, savings_jars: ?int} null = unlimited */
    public function freeLimits(): array
    {
        $free = Plan::default();

        return [
            'bills' => $free->bill_limit,
            'income_sources' => $free->income_source_limit,
            'savings_jars' => $free->savings_jar_limit,
        ];
    }

    /**
     * Items competing for the Free slots: those in use plus those archived
     * by a downgrade, in the order they are kept by default.
     *
     * @return array{bills: Collection<int, Bill>, income_sources: Collection<int, IncomeSource>, savings_jars: Collection<int, SavingsJar>}
     */
    public function candidates(User $user): array
    {
        return [
            'bills' => $user->bills()
                ->where(fn (Builder $q) => $q->where('is_active', true)->orWhere('archived_reason', Bill::ARCHIVED_BY_PLAN))
                ->orderByDesc('is_active')
                ->orderBy('id')
                ->get(),
            'income_sources' => $user->incomeSources()
                ->where(fn (Builder $q) => $q->where('is_active', true)->orWhere('archived_reason', IncomeSource::ARCHIVED_BY_PLAN))
                ->orderByDesc('is_active')
                ->orderByDesc('is_primary')
                ->orderBy('id')
                ->get(),
            'savings_jars' => $user->savingsJars()
                ->where(fn (Builder $q) => $q->where('is_archived', false)->orWhere('archived_reason', SavingsJar::ARCHIVED_BY_PLAN))
                ->orderBy('is_archived')
                ->orderBy('id')
                ->get(),
        ];
    }

    /**
     * Run when an account loses Pro. Keeps the first items up to each Free
     * limit (primary income first, then oldest) and archives the rest.
     * The user can change that choice afterwards with applySelection().
     */
    public function downgrade(User $user): void
    {
        if ($user->isPro()) {
            return;
        }

        $limits = $this->freeLimits();

        DB::transaction(function () use ($user, $limits) {
            $archivedAny = false;

            foreach ($this->candidates($user) as $type => $items) {
                $limit = $limits[$type];

                if (is_null($limit)) {
                    continue;
                }

                $items->filter(fn ($item) => $this->isInUse($item))
                    ->values()
                    ->slice($limit)
                    ->each(function ($item) use (&$archivedAny) {
                        $this->archive($item);
                        $archivedAny = true;
                    });
            }

            $this->ensurePrimaryIncome($user);

            if ($archivedAny) {
                $user->forceFill(['plan_selection_required_at' => now()])->save();
            }
        });
    }

    /**
     * The user picks which items stay active on Free. Everything else that
     * competes for a slot is archived; picked items that were archived come
     * back.
     *
     * @param  array{bills?: array<int>, income_sources?: array<int>, savings_jars?: array<int>}  $selection
     */
    public function applySelection(User $user, array $selection): void
    {
        $limits = $this->freeLimits();
        $candidates = $this->candidates($user);
        $errors = [];

        foreach (self::TYPES as $type) {
            $ids = array_values(array_unique(array_map('intval', $selection[$type] ?? [])));
            $selection[$type] = $ids;

            if (! is_null($limits[$type]) && count($ids) > $limits[$type]) {
                $errors[$type] = ["You can keep up to {$limits[$type]} on the Free plan."];
            }

            $unknown = array_diff($ids, $candidates[$type]->modelKeys());

            if ($unknown) {
                $errors[$type] = ['One or more selected items could not be found.'];
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        DB::transaction(function () use ($user, $selection, $candidates) {
            foreach ($candidates as $type => $items) {
                $keep = array_flip($selection[$type]);

                foreach ($items as $item) {
                    $selected = isset($keep[$item->getKey()]);

                    if ($selected && ! $this->isInUse($item)) {
                        $this->reactivate($item);
                    } elseif (! $selected && $this->isInUse($item)) {
                        $this->archive($item);
                    }
                }
            }

            $this->ensurePrimaryIncome($user);
            $user->forceFill(['plan_selection_required_at' => null])->save();
        });
    }

    /**
     * Bring back everything a downgrade archived. Only for Pro accounts;
     * the caller checks that.
     *
     * @return array{bills: int, income_sources: int, savings_jars: int}
     */
    public function restore(User $user): array
    {
        return DB::transaction(function () use ($user) {
            $restored = [
                'bills' => $this->reactivateAll($user->bills()->archivedByPlan()->get()),
                'income_sources' => $this->reactivateAll($user->incomeSources()->archivedByPlan()->get()),
                'savings_jars' => $this->reactivateAll($user->savingsJars()->archivedByPlan()->get()),
            ];

            $this->ensurePrimaryIncome($user);
            $user->forceFill(['plan_selection_required_at' => null])->save();

            return $restored;
        });
    }

    // ───────────────────────────── Internals ─────────────────────────────

    private function reactivateAll(Collection $items): int
    {
        $items->each(fn ($item) => $this->reactivate($item));

        return $items->count();
    }

    private function isInUse(Bill|IncomeSource|SavingsJar $item): bool
    {
        return $item instanceof SavingsJar ? ! $item->is_archived : (bool) $item->is_active;
    }

    private function archive(Bill|IncomeSource|SavingsJar $item): void
    {
        $item->archiveForPlan();

        // Drop the bill's future unpaid occurrences; paid history stays.
        if ($item instanceof Bill) {
            $this->scheduler->resync($item);
        }
    }

    private function reactivate(Bill|IncomeSource|SavingsJar $item): void
    {
        $item->reactivate();

        // Rebuild the bill's schedule so the calendar and plan see it again.
        if ($item instanceof Bill) {
            $this->scheduler->resync($item);
        }
    }

    /**
     * Payday maths reads the primary income. If it was archived, promote the
     * next active source and mirror it onto the user like IncomeSourceController does.
     */
    private function ensurePrimaryIncome(User $user): void
    {
        $active = $user->incomeSources()->active()->orderByDesc('is_primary')->orderBy('id')->get();
        $primary = $active->firstWhere('is_primary', true) ?? $active->first();

        if (! $primary) {
            return;
        }

        if (! $primary->is_primary) {
            $primary->forceFill(['is_primary' => true])->save();
        }

        $user->incomeSources()
            ->whereKeyNot($primary->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $user->forceFill([
            'pay_frequency' => $primary->pay_frequency,
            'next_payday' => $primary->next_payday,
            'estimated_paycheck_amount' => $primary->amount,
        ])->save();
    }
}
