<?php

namespace App\Services;

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
            "You have reached {$limit} bills on the free plan. Upgrade to add unlimited bills."
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
            'Multiple income sources are part of Brilliant Buddy Pro.'
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
            'Savings jars are part of Brilliant Buddy Pro.'
        );
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
