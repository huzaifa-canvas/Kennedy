<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\BillDueNotification;
use App\Notifications\BillStreakNotification;
use App\Notifications\PaydayReminderNotification;
use App\Notifications\SpendingInsightNotification;
use App\Services\BillScheduler;
use App\Services\SetAsideCalculator;
use App\Services\SubscriptionManager;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The one scheduled job for the whole app.
 *
 * Runs once a day and, in order:
 *   1. tops up the rolling window of bill occurrences and flags overdue ones
 *   2. reminds users about bills entering their reminder window
 *   3. tells users it is payday and how much to set aside
 *   4. congratulates an on-time streak
 *   5. compares this week's spending with last week's
 *   6. ends subscriptions past their end date (after re-checking RevenueCat)
 *      and moves those users to Free
 *   7. deletes notifications older than the retention window (15 days)
 *
 * Every task is idempotent, so running it twice in a day sends nothing twice.
 */
class RunSaverrCron extends Command
{
    /** Bills further out than this are not worth loading for reminders. */
    private const REMINDER_LOOKAHEAD_DAYS = 31;

    /** Window used for the on-time streak. */
    private const STREAK_DAYS = 14;

    /** Without RevenueCat's API, wait this long for a late renewal before ending Pro. */
    private const STORE_EXPIRY_BUFFER_HOURS = 24;

    /** Smallest week-on-week change worth a notification. */
    private const INSIGHT_THRESHOLD_PERCENT = 10;

    protected $signature = 'saverr:cron
                            {--only= : Run a single task (schedules, reminders, payday, streaks, insights, subscriptions, prune)}';

    protected $description = 'Run every scheduled task: bill schedules, notifications and cleanup';

    public function __construct(
        private readonly BillScheduler $scheduler,
        private readonly SetAsideCalculator $calculator,
        private readonly SubscriptionManager $subscriptions,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $only = $this->option('only');

        $tasks = [
            'schedules' => fn () => $this->refreshSchedules(),
            'reminders' => fn () => $this->sendBillReminders(),
            'payday' => fn () => $this->sendPaydayReminders(),
            'streaks' => fn () => $this->sendStreakNotifications(),
            'insights' => fn () => $this->sendSpendingInsights(),
            'subscriptions' => fn () => $this->expireSubscriptions(),
            'prune' => fn () => $this->pruneNotifications(),
        ];

        if ($only && ! isset($tasks[$only])) {
            $this->error("Unknown task \"{$only}\". Options: " . implode(', ', array_keys($tasks)));

            return self::FAILURE;
        }

        foreach ($tasks as $name => $task) {
            if ($only && $only !== $name) {
                continue;
            }

            $this->line("<fg=cyan>→</> {$name}");
            $task();
        }

        return self::SUCCESS;
    }

    // ───────────────────────── 1. Bill schedules ─────────────────────────

    private function refreshSchedules(): void
    {
        $flagged = $this->scheduler->markOverdue();
        $synced = 0;

        Bill::query()
            ->where('is_active', true)
            ->chunkById(200, function ($bills) use (&$synced) {
                foreach ($bills as $bill) {
                    $this->scheduler->sync($bill);
                    $synced++;
                }
            });

        $this->info("   {$synced} bill(s) refreshed, {$flagged} flagged overdue.");
    }

    // ───────────────────────── 2. Bill reminders ─────────────────────────

    private function sendBillReminders(): void
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(self::REMINDER_LOOKAHEAD_DAYS);
        $sent = 0;

        BillOccurrence::query()
            ->with(['bill', 'user'])
            ->unpaid()
            ->whereBetween('due_date', [$today->toDateString(), $horizon->toDateString()])
            ->whereHas('bill', fn ($query) => $query->where('is_active', true))
            ->chunkById(200, function ($occurrences) use (&$sent, $today) {
                foreach ($occurrences as $occurrence) {
                    $user = $occurrence->user;

                    if (! $this->canNotify($user)) {
                        continue;
                    }

                    // Only once the bill is inside its own reminder window.
                    if ($today->diffInDays($occurrence->due_date, false) > (int) $occurrence->bill->reminder_days_before) {
                        continue;
                    }

                    if ($this->alreadySent($user->id, BillDueNotification::class, $today, '"occurrence_id":' . $occurrence->id . ',')) {
                        continue;
                    }

                    $user->notify(new BillDueNotification($occurrence));
                    $sent++;
                }
            });

        $this->info("   {$sent} bill reminder(s) sent.");
    }

    // ───────────────────────── 3. Payday reminders ───────────────────────

    private function sendPaydayReminders(): void
    {
        $today = Carbon::today();
        $sent = 0;

        $this->eachNotifiableUser(function (User $user) use ($today, &$sent) {
            $payday = $this->calculator->nextPayday($user);

            if (! $payday || ! $payday->isSameDay($today)) {
                return;
            }

            if ($this->alreadySent($user->id, PaydayReminderNotification::class, $today)) {
                return;
            }

            $expected = $this->calculator->expectedPaycheckAmount($user);

            if ($expected <= 0) {
                return;
            }

            $user->notify(new PaydayReminderNotification(
                $expected,
                $this->calculator->setAside($user)['amount'],
            ));

            $sent++;
        });

        $this->info("   {$sent} payday reminder(s) sent.");
    }

    // ───────────────────────── 4. On-time streaks ────────────────────────

    private function sendStreakNotifications(): void
    {
        $since = Carbon::today()->subDays(self::STREAK_DAYS);
        $weeks = (int) (self::STREAK_DAYS / 7);
        $sent = 0;

        $this->eachNotifiableUser(function (User $user) use ($since, $weeks, &$sent) {
            if ($this->alreadySent($user->id, BillStreakNotification::class, $since)) {
                return;
            }

            $due = $user->billOccurrences()
                ->whereBetween('due_date', [$since->toDateString(), Carbon::today()->toDateString()])
                ->where('status', '!=', 'skipped')
                ->get(['status', 'due_date', 'paid_at']);

            // A streak needs a couple of bills to mean anything.
            if ($due->count() < 2) {
                return;
            }

            $allOnTime = $due->every(
                fn ($occurrence) => $occurrence->status === 'paid'
                    && $occurrence->paid_at
                    && $occurrence->paid_at->startOfDay()->lte($occurrence->due_date)
            );

            if (! $allOnTime) {
                return;
            }

            $user->notify(new BillStreakNotification($weeks));
            $sent++;
        });

        $this->info("   {$sent} streak notification(s) sent.");
    }

    // ───────────────────────── 5. Spending insights ──────────────────────

    private function sendSpendingInsights(): void
    {
        $today = Carbon::today();
        $thisWeekStart = $today->copy()->subDays(6);
        $lastWeekStart = $today->copy()->subDays(13);
        $lastWeekEnd = $today->copy()->subDays(7);
        $sent = 0;

        $this->eachNotifiableUser(function (User $user) use ($today, $thisWeekStart, $lastWeekStart, $lastWeekEnd, &$sent) {
            if ($this->alreadySent($user->id, SpendingInsightNotification::class, $today->copy()->subDays(6))) {
                return;
            }

            $thisWeek = (float) Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereBetween('occurred_on', [$thisWeekStart->toDateString(), $today->toDateString()])
                ->sum('amount');

            $lastWeek = (float) Transaction::where('user_id', $user->id)
                ->where('type', 'expense')
                ->whereBetween('occurred_on', [$lastWeekStart->toDateString(), $lastWeekEnd->toDateString()])
                ->sum('amount');

            if ($lastWeek <= 0) {
                return;
            }

            $change = (($thisWeek - $lastWeek) / $lastWeek) * 100;

            if (abs($change) < self::INSIGHT_THRESHOLD_PERCENT) {
                return;
            }

            $user->notify(new SpendingInsightNotification(
                (int) round(abs($change)),
                $change < 0,
            ));

            $sent++;
        });

        $this->info("   {$sent} spending insight(s) sent.");
    }

    // ───────────────────────── 6. Subscriptions ──────────────────────────

    /**
     * Safety net behind the RevenueCat webhook. For each subscription whose
     * access (grace period included) has run out:
     *   - store subscriptions are re-read from RevenueCat, which renews,
     *     keeps (billing retry) or expires them as the store decides;
     *   - if RevenueCat cannot be reached, a late renewal webhook gets a
     *     day before Pro is ended;
     *   - manual (admin) grants simply end.
     * Ending Pro runs the Free downgrade (archive over-limit data).
     */
    private function expireSubscriptions(): void
    {
        $expired = 0;
        $refreshed = 0;

        Subscription::query()
            ->with('user')
            ->accessEndedBefore(now())
            ->chunkById(100, function ($subscriptions) use (&$expired, &$refreshed) {
                foreach ($subscriptions as $subscription) {
                    if ($subscription->provider === 'revenuecat') {
                        if ($subscription->user && $this->subscriptions->refreshFromRevenueCat($subscription->user)) {
                            $refreshed++;

                            continue;
                        }

                        if ($subscription->accessEndsAt()?->gt(now()->subHours(self::STORE_EXPIRY_BUFFER_HOURS))) {
                            continue;
                        }
                    }

                    $this->subscriptions->expire($subscription);
                    $expired++;
                }
            });

        $this->info("   {$refreshed} re-checked with RevenueCat, {$expired} subscription(s) expired.");
    }

    // ───────────────────────── 7. Retention ──────────────────────────────

    private function pruneNotifications(): void
    {
        $days = max(1, (int) config('fcm.prune_after_days', 15));
        $cutoff = Carbon::now()->subDays($days);
        $deleted = 0;

        // Chunked so a large backlog never holds a long lock.
        do {
            $batch = DB::table('notifications')->where('created_at', '<', $cutoff)->limit(1000)->delete();
            $deleted += $batch;
        } while ($batch > 0);

        $this->info("   {$deleted} notification(s) older than {$days} day(s) removed.");
    }

    // ───────────────────────── Helpers ───────────────────────────────────

    private function eachNotifiableUser(callable $callback): void
    {
        User::query()
            ->customers()
            ->where('status', 'active')
            ->where('notifications_enabled', true)
            ->with('incomeSources')
            ->chunkById(200, function ($users) use ($callback) {
                foreach ($users as $user) {
                    $callback($user);
                }
            });
    }

    private function canNotify(?User $user): bool
    {
        return $user && $user->notifications_enabled && $user->isActive();
    }

    /** Guards every task against sending the same thing twice. */
    private function alreadySent(int $userId, string $notification, Carbon $since, ?string $dataContains = null): bool
    {
        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $userId)
            ->where('type', $notification)
            ->where('created_at', '>=', $since->copy()->startOfDay())
            ->when($dataContains, fn ($query) => $query->where('data', 'like', '%' . $dataContains . '%'))
            ->exists();
    }
}
