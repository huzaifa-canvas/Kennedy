<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SetAsideCalculator;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Screen 12 — the month calendar.
 *
 * Three kinds of marker, matching the design: a solid chip per bill in its
 * category colour, an orange Payday chip, and a dashed chip for money the
 * user should set aside on that payday.
 */
class CalendarController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SetAsideCalculator $calculator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $user = $request->user()->loadMissing('incomeSources');

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : Carbon::today()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $events = [];

        // Bills due this month.
        $occurrences = $user->billOccurrences()
            ->with('bill.category')
            ->forActiveBills()
            ->whereBetween('due_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', '!=', 'skipped')
            ->orderBy('due_date')
            ->get();

        foreach ($occurrences as $occurrence) {
            $events[$occurrence->due_date->toDateString()][] = [
                'type' => 'bill_due',
                'style' => 'solid',
                'label' => $occurrence->bill?->name ?? 'Bill',
                'amount' => (float) $occurrence->amount,
                'color' => $occurrence->bill?->color ?? $occurrence->bill?->category?->color ?? '#64748B',
                'status' => $occurrence->status,
                'bill_id' => $occurrence->bill_id,
                'occurrence_id' => $occurrence->id,
            ];
        }

        // Paydays, and what to hold back on each one.
        $paydays = $this->calculator->upcomingPaydays($user, 24)
            ->filter(fn (Carbon $date) => $date->between($start, $end));

        $setAside = $this->calculator->setAside($user);
        $nextPayday = $setAside['next_payday'];

        foreach ($paydays as $payday) {
            $events[$payday->toDateString()][] = [
                'type' => 'payday',
                'style' => 'solid',
                'label' => 'Payday',
                'amount' => $this->calculator->expectedPaycheckAmount($user),
                'color' => '#F97316',
            ];

            // The dashed set-aside markers only apply to the next payday.
            if ($payday->toDateString() !== $nextPayday) {
                continue;
            }

            foreach ($setAside['breakdown'] as $item) {
                $events[$payday->toDateString()][] = [
                    'type' => 'set_aside',
                    'style' => 'dashed',
                    'label' => $item['name'] ?? 'Set aside',
                    'amount' => $item['set_aside_amount'],
                    'color' => '#94A3B8',
                    'bill_id' => $item['bill_id'],
                ];
            }
        }

        $days = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->toDateString();

            $days[] = [
                'date' => $key,
                'day' => $date->day,
                'is_today' => $date->isToday(),
                'events' => $events[$key] ?? [],
            ];
        }

        return $this->ok([
            'month' => $month->format('Y-m'),
            'month_label' => $month->format('F Y'),
            'starts_on' => $start->dayOfWeek,
            'days_in_month' => $start->daysInMonth,
            'days' => $days,
            'summary' => [
                'bills_total' => round((float) $occurrences->sum('amount'), 2),
                'bills_count' => $occurrences->count(),
                'paydays_count' => $paydays->count(),
                'set_aside_total' => $setAside['amount'],
            ],
        ]);
    }
}
