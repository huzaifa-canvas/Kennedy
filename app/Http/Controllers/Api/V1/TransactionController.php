<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Services\PlanGate;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TransactionController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly PlanGate $gate)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'in:income,expense,all'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $user = $request->user();
        $earliest = $this->gate->historyWindowStart($user);

        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])
            : Carbon::today()->subDays(30);

        if ($earliest && $from->lt($earliest)) {
            $from = $earliest;
        }

        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::today();

        $query = $user->transactions()
            ->with('category')
            ->whereBetween('occurred_on', [$from->toDateString(), $to->toDateString()]);

        if (! empty($validated['type']) && $validated['type'] !== 'all') {
            $query->where('type', $validated['type']);
        }

        $transactions = $query
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        return $this->paginated(TransactionResource::collection($transactions));
    }
}
