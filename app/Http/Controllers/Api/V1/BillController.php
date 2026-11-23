<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreBillRequest;
use App\Http\Requests\Api\UpdateBillRequest;
use App\Http\Resources\BillOccurrenceResource;
use App\Http\Resources\BillResource;
use App\Models\Bill;
use App\Services\BillScheduler;
use App\Services\PlanGate;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Screens 10 (Bills) and 11 (Add Bill).
 */
class BillController extends Controller
{
    use ApiResponses;

    /** Only these columns may ever reach an ORDER BY. */
    private const SORTABLE = ['next_due_date', 'due_date', 'amount', 'name', 'created_at'];

    public function __construct(
        private readonly BillScheduler $scheduler,
        private readonly PlanGate $gate,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer', 'exists:bill_categories,id'],
            'status' => ['nullable', 'in:active,inactive,all'],
            'sort_by' => ['nullable', 'in:' . implode(',', self::SORTABLE)],
            'sort_dir' => ['nullable', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $request->user()->bills()->with('category');

        if (! empty($validated['search'])) {
            // Bound parameter; the wildcards are added to the value, not the SQL.
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $validated['search']) . '%';
            $query->where('name', 'like', $term);
        }

        if (! empty($validated['category_id'])) {
            $query->where('bill_category_id', $validated['category_id']);
        }

        $status = $validated['status'] ?? 'active';

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        $sortBy = $validated['sort_by'] ?? 'next_due_date';
        $sortDir = $validated['sort_dir'] ?? 'asc';

        $bills = $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($validated['per_page'] ?? 20)
            ->withQueryString();

        return $this->paginated(BillResource::collection($bills));
    }

    /** The three totals across the top of the Bills screen. */
    public function summary(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = isset($validated['month'])
            ? Carbon::createFromFormat('Y-m', $validated['month'])->startOfMonth()
            : Carbon::today()->startOfMonth();

        $rows = $request->user()->billOccurrences()
            ->forActiveBills()
            ->whereBetween('due_date', [
                $month->toDateString(),
                $month->copy()->endOfMonth()->toDateString(),
            ])
            ->where('status', '!=', 'skipped')
            ->get(['amount', 'paid_amount', 'status']);

        $total = (float) $rows->sum('amount');
        $paid = (float) $rows->where('status', 'paid')
            ->sum(fn ($row) => $row->paid_amount ?? $row->amount);

        return $this->ok([
            'month' => $month->format('Y-m'),
            'total' => round($total, 2),
            'paid' => round($paid, 2),
            'unpaid' => round($total - $paid, 2),
        ]);
    }

    public function store(StoreBillRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $verdict = $this->gate->canAddBill($user);

        if (! $verdict['allowed']) {
            throw new PlanLimitException($verdict['reason'], $verdict['limit'], $verdict['used'], 'bills');
        }

        if (! $this->gate->canUseRecurrence($user, $data['recurrence'])) {
            throw new PlanLimitException(
                'Weekly and bi-weekly recurrence requires a subscription.',
                feature: 'recurrence'
            );
        }

        $bill = DB::transaction(function () use ($user, $data) {
            $bill = $user->bills()->create($data);
            $this->scheduler->sync($bill);

            return $bill;
        });

        return $this->created(
            new BillResource($bill->fresh('category')),
            'Bill added successfully.'
        );
    }

    public function show(Request $request, Bill $bill): JsonResponse
    {
        $this->authorize('view', $bill);

        $bill->load('category');

        $occurrences = $bill->occurrences()
            ->with('bill.category')
            ->orderBy('due_date')
            ->limit(24)
            ->get();

        return $this->ok([
            'bill' => new BillResource($bill),
            'occurrences' => BillOccurrenceResource::collection($occurrences),
        ]);
    }

    public function update(UpdateBillRequest $request, Bill $bill): JsonResponse
    {
        $this->authorize('update', $bill);

        $data = $request->validated();

        if (isset($data['recurrence']) && ! $this->gate->canUseRecurrence($request->user(), $data['recurrence'])) {
            throw new PlanLimitException(
                'Weekly and bi-weekly recurrence requires a subscription.',
                feature: 'recurrence'
            );
        }

        if (filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $bill->is_active) {
            $this->gate->ensureCanReactivate($request->user(), 'bills');
            $data += ['archived_at' => null, 'archived_reason' => null];
        }

        // Only a change to the schedule or amount needs occurrences rebuilt.
        $needsResync = (bool) array_intersect(
            array_keys($data),
            ['amount', 'due_date', 'recurrence', 'is_active']
        );

        DB::transaction(function () use ($bill, $data, $needsResync) {
            $bill->update($data);

            if ($needsResync) {
                $this->scheduler->resync($bill);
            }
        });

        return $this->ok(
            new BillResource($bill->fresh('category')),
            'Bill updated successfully.'
        );
    }

    public function destroy(Request $request, Bill $bill): JsonResponse
    {
        $this->authorize('delete', $bill);

        DB::transaction(function () use ($bill) {
            // Keep paid history; drop everything still pending.
            $bill->occurrences()->where('status', '!=', 'paid')->delete();
            $bill->delete();
        });

        return $this->ok(null, 'Bill deleted successfully.');
    }
}
