<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillCategory;
use App\Models\BillOccurrence;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Read-only oversight of every user's bills. Editing a customer's bill is
 * deliberately left to the mobile app.
 */
class BillController extends Controller
{
    private const SORTABLE = ['created_at', 'amount', 'next_due_date', 'name'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:bill_categories,id'],
            'recurrence' => ['nullable', 'in:one_time,weekly,biweekly,monthly'],
            'sort_by' => ['nullable', 'in:' . implode(',', self::SORTABLE)],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = Bill::query()->with(['user:id,name,email', 'category']);

        if (! empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
            });
        }

        if (! empty($filters['category_id'])) {
            $query->where('bill_category_id', $filters['category_id']);
        }

        if (! empty($filters['recurrence'])) {
            $query->where('recurrence', $filters['recurrence']);
        }

        $bills = $query
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate(20)
            ->withQueryString();

        $monthStart = Carbon::today()->startOfMonth();
        $monthEnd = Carbon::today()->endOfMonth();

        $occurrences = BillOccurrence::whereBetween('due_date', [$monthStart, $monthEnd])
            ->where('status', '!=', 'skipped')
            ->get(['amount', 'status', 'paid_amount']);

        $totals = [
            'bills' => Bill::where('is_active', true)->count(),
            'due_this_month' => round((float) $occurrences->sum('amount'), 2),
            'paid_this_month' => round((float) $occurrences->where('status', 'paid')
                ->sum(fn ($row) => $row->paid_amount ?? $row->amount), 2),
            'overdue' => BillOccurrence::where('status', 'overdue')->count(),
        ];

        $categories = BillCategory::orderBy('sort_order')->get();

        return view('content.admin.bills.index', compact('bills', 'totals', 'categories', 'filters'));
    }

    public function show(Bill $bill): View
    {
        $bill->load(['user:id,name,email', 'category']);

        $occurrences = $bill->occurrences()->orderBy('due_date')->limit(36)->get();

        return view('content.admin.bills.show', compact('bill', 'occurrences'));
    }
}
