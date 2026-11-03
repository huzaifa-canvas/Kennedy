<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillOccurrence;
use App\Models\SavingsJar;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        $stats = [
            'total_users' => User::customers()->count(),
            'new_users_this_month' => User::customers()->where('created_at', '>=', $monthStart)->count(),
            'active_subscriptions' => Subscription::active()->count(),
            'suspended_users' => User::customers()->where('status', 'suspended')->count(),
            'total_bills' => Bill::where('is_active', true)->count(),
            'bills_due_this_month' => (float) BillOccurrence::whereBetween('due_date', [$monthStart, $monthEnd])
                ->where('status', '!=', 'skipped')
                ->sum('amount'),
            'savings_jars' => SavingsJar::where('is_archived', false)->count(),
            'total_saved' => (float) SavingsJar::where('is_archived', false)->sum('saved_amount'),
        ];

        $stats['mrr'] = (float) Subscription::active()
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('plans.billing_period', 'monthly')
            ->sum('plans.price');

        // Signups over the last 12 months.
        $signupSeries = User::customers()
            ->where('created_at', '>=', Carbon::today()->subMonths(11)->startOfMonth())
            ->get(['created_at'])
            ->groupBy(fn ($user) => $user->created_at->format('Y-m'))
            ->map->count();

        $months = collect(range(11, 0))->map(function (int $back) use ($signupSeries) {
            $month = Carbon::today()->subMonths($back)->startOfMonth();

            return [
                'label' => $month->format('M'),
                'key' => $month->format('Y-m'),
                'count' => (int) ($signupSeries[$month->format('Y-m')] ?? 0),
            ];
        });

        $planBreakdown = User::customers()->get()
            ->groupBy(fn (User $user) => $user->plan()->name)
            ->map->count();

        $recentUsers = User::customers()->latest()->limit(8)->get();

        $topCategories = BillOccurrence::query()
            ->join('bills', 'bills.id', '=', 'bill_occurrences.bill_id')
            ->leftJoin('bill_categories', 'bill_categories.id', '=', 'bills.bill_category_id')
            ->whereBetween('bill_occurrences.due_date', [$monthStart, $monthEnd])
            ->groupBy('bill_categories.name', 'bill_categories.color')
            ->selectRaw('COALESCE(bill_categories.name, ?) as name, bill_categories.color as color, SUM(bill_occurrences.amount) as total', ['Uncategorised'])
            ->orderByDesc('total')
            ->limit(6)
            ->get();

        return view('content.admin.dashboard', compact(
            'stats', 'months', 'planBreakdown', 'recentUsers', 'topCategories'
        ));
    }
}
