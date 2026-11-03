<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PayBillRequest;
use App\Http\Resources\BillOccurrenceResource;
use App\Models\BillOccurrence;
use App\Models\Transaction;
use App\Notifications\BillPaidNotification;
use App\Services\BillScheduler;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Individual dated bills — what the Bills list ticks off and what the
 * calendar and My Plan screens read.
 */
class BillOccurrenceController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly BillScheduler $scheduler)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'status' => ['nullable', 'in:upcoming,paid,overdue,skipped,unpaid,all'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::today()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : $from->copy()->endOfMonth();

        $query = $request->user()->billOccurrences()
            ->with('bill.category')
            ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()]);

        $status = $validated['status'] ?? 'all';

        if ($status === 'unpaid') {
            $query->unpaid();
        } elseif ($status !== 'all') {
            $query->where('status', $status);
        }

        $occurrences = $query
            ->orderBy('due_date')
            ->paginate($validated['per_page'] ?? 50)
            ->withQueryString();

        return $this->paginated(BillOccurrenceResource::collection($occurrences));
    }

    public function pay(PayBillRequest $request, BillOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);

        if ($occurrence->status === 'paid') {
            return $this->fail('This bill is already marked as paid.', 409);
        }

        $occurrence->loadMissing('bill.category');

        DB::transaction(function () use ($request, $occurrence) {
            $paidAmount = $request->validated('paid_amount') ?? (float) $occurrence->amount;
            $paidAt = $request->validated('paid_at') ?? now();

            $occurrence->update([
                'status' => 'paid',
                'paid_amount' => $paidAmount,
                'paid_at' => $paidAt,
            ]);

            Transaction::create([
                'user_id' => $occurrence->user_id,
                'type' => 'expense',
                'title' => $occurrence->bill?->name ?? 'Bill payment',
                'amount' => $paidAmount,
                'occurred_on' => Carbon::parse($paidAt)->toDateString(),
                'bill_id' => $occurrence->bill_id,
                'bill_occurrence_id' => $occurrence->id,
                'bill_category_id' => $occurrence->bill?->bill_category_id,
                'icon' => $occurrence->bill?->category?->icon,
            ]);

            if ($occurrence->bill) {
                $this->scheduler->refreshNextDueDate($occurrence->bill);
            }
        });

        $occurrence->refresh()->loadMissing('bill.category');
        $request->user()->notify(new BillPaidNotification($occurrence));

        return $this->ok(
            new BillOccurrenceResource($occurrence),
            'Payment recorded successfully.'
        );
    }

    public function unpay(Request $request, BillOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);

        if ($occurrence->status !== 'paid') {
            return $this->fail('This bill is not marked as paid.', 409);
        }

        DB::transaction(function () use ($occurrence) {
            Transaction::where('bill_occurrence_id', $occurrence->id)->delete();

            $occurrence->update([
                'status' => $occurrence->due_date->isBefore(Carbon::today()) ? 'overdue' : 'upcoming',
                'paid_amount' => null,
                'paid_at' => null,
            ]);

            if ($occurrence->bill) {
                $this->scheduler->refreshNextDueDate($occurrence->bill);
            }
        });

        return $this->ok(
            new BillOccurrenceResource($occurrence->fresh('bill.category')),
            'Payment reverted.'
        );
    }

    public function skip(Request $request, BillOccurrence $occurrence): JsonResponse
    {
        $this->authorize('update', $occurrence);

        if ($occurrence->status === 'paid') {
            return $this->fail('A paid bill cannot be skipped.', 409);
        }

        $occurrence->update(['status' => 'skipped']);

        if ($occurrence->bill) {
            $this->scheduler->refreshNextDueDate($occurrence->bill);
        }

        return $this->ok(
            new BillOccurrenceResource($occurrence->fresh('bill.category')),
            'Bill skipped for this cycle.'
        );
    }
}
