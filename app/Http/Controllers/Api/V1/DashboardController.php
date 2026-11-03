<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\BillOccurrenceResource;
use App\Services\SetAsideCalculator;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Screen 08 — the home dashboard.
 */
class DashboardController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SetAsideCalculator $calculator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->loadMissing('incomeSources');

        $setAside = $this->calculator->setAside($user);
        $allowance = $this->calculator->allowance($user, $setAside['amount']);
        $overview = $this->calculator->monthlyOverview($user);

        $upcoming = $user->billOccurrences()
            ->with('bill.category')
            ->unpaid()
            ->whereDate('due_date', '>=', Carbon::today())
            ->orderBy('due_date')
            ->limit(3)
            ->get();

        return $this->ok([
            'greeting' => [
                'salutation' => $this->salutation($user->timezone),
                'name' => $user->name,
                'subtitle' => 'Stay On Top Of Your Finances.',
            ],
            'set_aside' => [
                'amount' => $setAside['amount'],
                'label' => 'Set Aside',
                'description' => 'Set aside this amount to stay on track for your upcoming bills.',
                'next_paycheck' => $setAside['next_payday'],
                'covers_until' => $setAside['covers_until'],
                'smart_splits' => $setAside['smart_splits'],
            ],
            'allowance' => $allowance,
            'upcoming_bills' => BillOccurrenceResource::collection($upcoming),
            'overview' => [
                'monthly_income' => $overview['income'],
                'remaining_balance' => $overview['remaining'],
                'monthly_bills' => $overview['bills'],
                'period' => 'This Month',
            ],
            'unread_notifications' => $user->unreadNotifications()->count(),
            'onboarding_completed' => ! is_null($user->onboarding_completed_at),
        ]);
    }

    private function salutation(?string $timezone): string
    {
        $hour = Carbon::now($timezone ?: config('app.timezone'))->hour;

        return match (true) {
            $hour < 12 => 'Good Morning',
            $hour < 17 => 'Good Afternoon',
            default => 'Good Evening',
        };
    }
}
