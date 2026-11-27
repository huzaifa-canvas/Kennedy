<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePaycheckRequest;
use App\Http\Resources\PaycheckResource;
use App\Models\Transaction;
use App\Notifications\PaycheckReceivedNotification;
use App\Services\SetAsideCalculator;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recording a paycheck rolls the payday forward and tells the user how
 * much of it to set aside.
 */
class PaycheckController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly SetAsideCalculator $calculator)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $paychecks = $request->user()->paychecks()
            ->with('incomeSource')
            ->orderByDesc('received_on')
            ->paginate($validated['per_page'] ?? 20);

        return $this->paginated(PaycheckResource::collection($paychecks));
    }

    public function store(StorePaycheckRequest $request): JsonResponse
    {
        $user = $request->user()->loadMissing('incomeSources');
        $data = $request->validated();

        $setAside = $this->calculator->setAside($user)['amount'];
        $receivedOn = isset($data['received_on'])
            ? Carbon::parse($data['received_on'])
            : Carbon::today();

        $paycheck = DB::transaction(function () use ($user, $data, $receivedOn, $setAside) {
            $paycheck = $user->paychecks()->create([
                'income_source_id' => $data['income_source_id'] ?? $user->primaryIncome()?->id,
                'amount' => $data['amount'],
                'set_aside_amount' => $setAside,
                'received_on' => $receivedOn->toDateString(),
            ]);

            Transaction::create([
                'user_id' => $user->id,
                'type' => 'income',
                'title' => $paycheck->incomeSource?->name ?? 'Paycheck',
                'amount' => $paycheck->amount,
                'occurred_on' => $receivedOn->toDateString(),
                'income_source_id' => $paycheck->income_source_id,
                'paycheck_id' => $paycheck->id,
                'icon' => 'tabler-cash',
            ]);

            // Move the schedule on so the next payday is in the future.
            $source = $paycheck->incomeSource;

            if ($source && $source->next_payday && $source->isRecurring()) {
                $next = $source->advancePayday($source->next_payday);

                while ($next->lte($receivedOn)) {
                    $next = $source->advancePayday($next);
                }

                $source->update(['next_payday' => $next]);

                if ($source->is_primary) {
                    $user->forceFill(['next_payday' => $next])->save();
                }
            }

            return $paycheck;
        });

        $user->notify(new PaycheckReceivedNotification($paycheck, $setAside));

        return $this->created([
            'paycheck' => new PaycheckResource($paycheck->fresh('incomeSource')),
            'set_aside' => $this->calculator->setAside($user->fresh()->loadMissing('incomeSources')),
        ], 'Paycheck recorded successfully.');
    }
}
