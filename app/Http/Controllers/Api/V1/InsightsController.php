<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillOccurrenceResource;
use App\Http\Resources\TransactionResource;
use App\Services\PlanGate;
use App\Services\SetAsideCalculator;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The Insights screen (totals, income-vs-expense split, history) and the
 * My Plan screen (this month's bills against this month's income).
 */
class InsightsController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly SetAsideCalculator $calculator,
        private readonly PlanGate $gate,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $user = $request->user();
        $days = $validated['days'] ?? 30;

        $earliest = $this->gate->historyWindowStart($user);
        $from = Carbon::today()->subDays($days);
        $capped = false;

        // Free plans only see the last 30 days.
        if ($earliest && $from->lt($earliest)) {
            $from = $earliest;
            $capped = true;
        }

        $to = Carbon::today();

        $transactions = $user->transactions()
            ->with('category')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->get();

        $income = round((float) $transactions->where('type', 'income')->sum('amount'), 2);
        $expenses = round((float) $transactions->where('type', 'expense')->sum('amount'), 2);
        $gross = $income + $expenses;

        $recent = $user->transactions()
            ->with('category')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $byCategory = $transactions
            ->where('type', 'expense')
            ->groupBy(fn ($row) => $row->category?->name ?? 'Other')
            ->map(fn ($rows, $name) => [
                'category' => $name,
                'amount' => round((float) $rows->sum('amount'), 2),
                'color' => $rows->first()->category?->color ?? '#64748B',
            ])
            ->sortByDesc('amount')
            ->values();

        return $this->ok([
            'range' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => (int) $from->diffInDays($to),
                'capped_by_plan' => $capped,
            ],
            'totals' => [
                'total_in' => $income,
                'total_out' => $expenses,
                'net' => round($income - $expenses, 2),
            ],
            'split' => [
                'income_percent' => $gross > 0 ? (int) round(($income / $gross) * 100) : 0,
                'expense_percent' => $gross > 0 ? (int) round(($expenses / $gross) * 100) : 0,
                'spent_this_week' => round((float) $user->transactions()
                    ->where('type', 'expense')
                    ->whereBetween('occurred_on', [
                        Carbon::today()->startOfWeek()->toDateString(),
                        Carbon::today()->endOfWeek()->toDateString(),
                    ])
                    ->sum('amount'), 2),
            ],
            'by_category' => $byCategory,
            'transactions' => TransactionResource::collection($recent),
        ]);
    }

    /** Screen "My Plan". */
    public function myPlan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = $request->user()->loadMissing('incomeSources');

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : Carbon::today()->startOfMonth();

        $overview = $this->calculator->monthlyOverview($user, $month);

        $occurrences = $user->billOccurrences()
            ->with('bill.category')
            ->whereBetween('due_date', [
                $month->copy()->startOfMonth()->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->where('status', '!=', 'skipped')
            ->orderBy('due_date')
            ->get();

        return $this->ok([
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'total_income' => $overview['income'],
            'total_bills' => $overview['bills'],
            'amount_left' => $overview['remaining'],
            'paid' => $overview['paid'],
            'unpaid' => $overview['unpaid'],
            'bills' => BillOccurrenceResource::collection($occurrences),
        ]);
    }
}
