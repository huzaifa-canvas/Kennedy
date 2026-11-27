<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OnboardingRequest;
use App\Http\Resources\BillCategoryResource;
use App\Http\Resources\UserResource;
use App\Models\BillCategory;
use App\Services\BillScheduler;
use App\Services\PlanGate;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Setup screens 05 (pay frequency), 06 (next payday) and 07 (top bills).
 * Each section is optional so the app can submit step by step.
 */
class OnboardingController extends Controller
{
    use ApiResponses;

    public function __construct(
        private readonly BillScheduler $scheduler,
        private readonly PlanGate $gate,
    ) {
    }

    /** Quick-pick chips for the "Add your top bills" step. */
    public function quickPicks(): JsonResponse
    {
        return $this->ok(
            BillCategoryResource::collection(
                BillCategory::active()->where('is_quick_pick', true)->orderBy('sort_order')->get()
            )
        );
    }

    public function store(OnboardingRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        DB::transaction(function () use ($user, $data) {
            $profile = [];

            if (array_key_exists('pay_frequency', $data)) {
                $profile['pay_frequency'] = $data['pay_frequency'];
            }

            if (array_key_exists('next_payday', $data)) {
                $profile['next_payday'] = $data['next_payday'];
            }

            if (array_key_exists('estimated_amount', $data)) {
                $profile['estimated_paycheck_amount'] = $data['estimated_amount'];
            }

            if ($profile !== []) {
                $user->fill($profile)->save();
                $this->syncPrimaryIncome($user);
            }

            foreach ($data['bills'] ?? [] as $row) {
                $verdict = $this->gate->canAddBill($user);

                if (! $verdict['allowed']) {
                    throw new PlanLimitException($verdict['reason'], $verdict['limit'], $verdict['used'], 'bills');
                }

                $bill = $user->bills()->create([
                    'name' => $row['name'],
                    'amount' => $row['amount'],
                    'due_date' => $row['due_date'],
                    'recurrence' => $row['recurrence'],
                    'bill_category_id' => $row['bill_category_id'] ?? null,
                ]);

                $this->scheduler->sync($bill);
            }

            if (! empty($data['complete'])) {
                $user->forceFill(['onboarding_completed_at' => now()])->save();
            }
        });

        return $this->ok(
            new UserResource($user->fresh()),
            ! empty($data['complete']) ? 'Setup complete.' : 'Saved.'
        );
    }

    public function complete(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->forceFill(['onboarding_completed_at' => now()])->save();

        return $this->ok(new UserResource($user->fresh()), 'Setup complete.');
    }

    /**
     * Mirror the onboarding pay schedule onto a primary income source so
     * every later calculation reads from one place.
     */
    private function syncPrimaryIncome($user): void
    {
        if (! $user->pay_frequency) {
            return;
        }

        $primary = $user->incomeSources()->where('is_primary', true)->first();

        $attributes = [
            'name' => $primary?->name ?? 'Main Job',
            'amount' => $user->estimated_paycheck_amount ?? $primary?->amount ?? 0,
            'pay_frequency' => $user->pay_frequency,
            'next_payday' => $user->next_payday,
            'is_primary' => true,
            'is_active' => true,
        ];

        if ($primary) {
            $primary->update($attributes);

            return;
        }

        $user->incomeSources()->create($attributes);
    }
}
