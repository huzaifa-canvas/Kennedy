<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddFundsRequest;
use App\Http\Requests\Api\StoreSavingsJarRequest;
use App\Http\Requests\Api\UpdateSavingsJarRequest;
use App\Http\Resources\SavingsJarResource;
use App\Http\Resources\SavingsTransactionResource;
use App\Models\SavingsJar;
use App\Notifications\SavingsMilestoneNotification;
use App\Services\PlanGate;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Screens 13 (Savings Jars), 15 (Add Funds) and Edit Jar.
 */
class SavingsJarController extends Controller
{
    use ApiResponses;

    /** Progress points worth a notification. */
    private const MILESTONES = [25, 50, 75, 100];

    public function __construct(private readonly PlanGate $gate)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'include_archived' => ['nullable', 'boolean'],
        ]);

        $jars = $request->user()->savingsJars()
            ->when(
                empty($validated['include_archived']),
                fn ($query) => $query->where('is_archived', false)
            )
            ->orderBy('created_at')
            ->get();

        return $this->ok([
            'jars' => SavingsJarResource::collection($jars),
            'totals' => [
                'saved' => round((float) $jars->sum('saved_amount'), 2),
                'target' => round((float) $jars->sum('target_amount'), 2),
            ],
        ]);
    }

    public function store(StoreSavingsJarRequest $request): JsonResponse
    {
        $user = $request->user();
        $verdict = $this->gate->canAddSavingsJar($user);

        if (! $verdict['allowed']) {
            throw new PlanLimitException($verdict['reason'], $verdict['limit'], $verdict['used'], 'savings_jars');
        }

        $data = $request->validated();
        $saved = (float) ($data['saved_amount'] ?? 0);

        $jar = DB::transaction(function () use ($user, $data, $saved) {
            $jar = $user->savingsJars()->create([
                'name' => $data['name'],
                'target_amount' => $data['target_amount'],
                'saved_amount' => $saved,
                'icon' => $data['icon'] ?? 'tabler-target',
                'color' => $data['color'] ?? '#F97316',
            ]);

            if ($saved > 0) {
                $jar->transactions()->create([
                    'user_id' => $user->id,
                    'type' => 'deposit',
                    'amount' => $saved,
                    'note' => 'Starting balance',
                    'occurred_on' => Carbon::today()->toDateString(),
                ]);
            }

            return $jar;
        });

        return $this->created(new SavingsJarResource($jar), 'Savings jar created.');
    }

    public function show(Request $request, SavingsJar $savingsJar): JsonResponse
    {
        $this->authorize('view', $savingsJar);

        return $this->ok([
            'jar' => new SavingsJarResource($savingsJar),
            'transactions' => SavingsTransactionResource::collection(
                $savingsJar->transactions()->orderByDesc('occurred_on')->limit(100)->get()
            ),
        ]);
    }

    public function update(UpdateSavingsJarRequest $request, SavingsJar $savingsJar): JsonResponse
    {
        $this->authorize('update', $savingsJar);

        $savingsJar->update($request->validated());
        $this->refreshCompletion($savingsJar);

        return $this->ok(new SavingsJarResource($savingsJar->fresh()), 'Savings jar updated.');
    }

    public function destroy(Request $request, SavingsJar $savingsJar): JsonResponse
    {
        $this->authorize('delete', $savingsJar);

        $savingsJar->delete();

        return $this->ok(null, 'Savings jar deleted.');
    }

    /** Screen 15 — Add Funds. */
    public function addFunds(AddFundsRequest $request, SavingsJar $savingsJar): JsonResponse
    {
        $this->authorize('update', $savingsJar);

        $before = $savingsJar->progress_percent;
        $amount = (float) $request->validated('amount');

        DB::transaction(function () use ($request, $savingsJar, $amount) {
            $savingsJar->transactions()->create([
                'user_id' => $request->user()->id,
                'type' => 'deposit',
                'amount' => $amount,
                'note' => $request->validated('note'),
                'occurred_on' => $request->validated('occurred_on') ?? Carbon::today()->toDateString(),
            ]);

            $savingsJar->increment('saved_amount', $amount);
        });

        $savingsJar->refresh();
        $this->refreshCompletion($savingsJar);
        $this->notifyMilestone($request->user(), $savingsJar, $before);

        return $this->ok(new SavingsJarResource($savingsJar->fresh()), 'Funds added successfully.');
    }

    public function withdrawFunds(AddFundsRequest $request, SavingsJar $savingsJar): JsonResponse
    {
        $this->authorize('update', $savingsJar);

        $amount = (float) $request->validated('amount');

        if ($amount > (float) $savingsJar->saved_amount) {
            return $this->fail('You cannot withdraw more than this jar holds.', 422);
        }

        DB::transaction(function () use ($request, $savingsJar, $amount) {
            $savingsJar->transactions()->create([
                'user_id' => $request->user()->id,
                'type' => 'withdrawal',
                'amount' => $amount,
                'note' => $request->validated('note'),
                'occurred_on' => $request->validated('occurred_on') ?? Carbon::today()->toDateString(),
            ]);

            $savingsJar->decrement('saved_amount', $amount);
        });

        $savingsJar->refresh();
        $this->refreshCompletion($savingsJar);

        return $this->ok(new SavingsJarResource($savingsJar->fresh()), 'Funds withdrawn.');
    }

    private function refreshCompletion(SavingsJar $jar): void
    {
        $isComplete = (float) $jar->saved_amount >= (float) $jar->target_amount;

        if ($isComplete && ! $jar->completed_at) {
            $jar->forceFill(['completed_at' => now()])->save();
        } elseif (! $isComplete && $jar->completed_at) {
            $jar->forceFill(['completed_at' => null])->save();
        }
    }

    private function notifyMilestone($user, SavingsJar $jar, int $before): void
    {
        $after = $jar->progress_percent;

        foreach (self::MILESTONES as $milestone) {
            if ($before < $milestone && $after >= $milestone) {
                $user->notify(new SavingsMilestoneNotification($jar));
                break;
            }
        }
    }
}
