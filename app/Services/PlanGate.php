<?php

namespace App\Services;

use App\Exceptions\PlanLimitException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Single place where free-vs-pro limits are decided, so the API, the
 * admin panel and the paywall copy can never drift apart.
 */
class PlanGate
{
    public function plan(User $user): Plan
    {
        return $user->plan();
    }

    /** @return array{allowed: bool, limit: ?int, used: int, reason: ?string} */
    public function canAddBill(User $user): array
    {
        $limit = $this->plan($user)->bill_limit;
        $used = $user->bills()->where('is_active', true)->count();

        return $this->verdict(
            $limit,
            $used,
            "You have reached {$limit} active bills on the Free plan. Upgrade to Saverr Pro for unlimited bills."
        );
    }

    /** @return array{allowed: bool, limit: ?int, used: int, reason: ?string} */
    public function canAddIncomeSource(User $user): array
    {
        $limit = $this->plan($user)->income_source_limit;
        $used = $user->incomeSources()->where('is_active', true)->count();

        return $this->verdict(
            $limit,
            $used,
            "You have reached {$limit} active income entries on the Free plan. Upgrade to Saverr Pro for more."
        );
    }

    /** @return array{allowed: bool, limit: ?int, used: int, reason: ?string} */
    public function canAddSavingsJar(User $user): array
    {
        $limit = $this->plan($user)->savings_jar_limit;
        $used = $user->savingsJars()->where('is_archived', false)->count();

        return $this->verdict(
            $limit,
            $used,
            $limit === 0
                ? 'Savings jars are part of Saverr Pro.'
                : "You have reached {$limit} active savings jar on the Free plan. Upgrade to Saverr Pro for more."
        );
    }

    /**
     * Turning a paused or plan-archived item back on takes a Free slot just
     * like creating one, so it is checked the same way.
     *
     * @throws PlanLimitException
     */
    public function ensureCanReactivate(User $user, string $type): void
    {
        $verdict = match ($type) {
            'bills' => $this->canAddBill($user),
            'income_sources' => $this->canAddIncomeSource($user),
            'savings_jars' => $this->canAddSavingsJar($user),
        };

        if (! $verdict['allowed']) {
            throw new PlanLimitException($verdict['reason'], $verdict['limit'], $verdict['used'], $type);
        }
    }

    /** Weekly and bi-weekly recurrence need a subscription. */
    public function canUseRecurrence(User $user, string $recurrence): bool
    {
        if (in_array($recurrence, ['one_time', 'monthly'], true)) {
            return true;
        }

        return (bool) $this->plan($user)->allows_advanced_recurrence;
    }

    public function canUseCustomReminders(User $user): bool
    {
        return (bool) $this->plan($user)->allows_custom_reminders;
    }

    /** Oldest date a user may read history from, or null for unlimited. */
    public function historyWindowStart(User $user): ?Carbon
    {
        $days = $this->plan($user)->history_days;

        return $days ? Carbon::today()->subDays($days) : null;
    }

    /** @return array{allowed: bool, limit: ?int, used: int, reason: ?string} */
    protected function verdict(?int $limit, int $used, string $reason): array
    {
        $allowed = is_null($limit) || $used < $limit;

        return [
            'allowed' => $allowed,
            'limit' => $limit,
            'used' => $used,
            'reason' => $allowed ? null : $reason,
        ];
    }
}
