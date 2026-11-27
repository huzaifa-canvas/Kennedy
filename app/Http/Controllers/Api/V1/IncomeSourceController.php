<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\PlanLimitException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreIncomeSourceRequest;
use App\Http\Requests\Api\UpdateIncomeSourceRequest;
use App\Http\Resources\IncomeSourceResource;
use App\Models\IncomeSource;
use App\Services\PlanGate;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Screen 09 — Add Income.
 */
class IncomeSourceController extends Controller
{
    use ApiResponses;

    public function __construct(private readonly PlanGate $gate)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $sources = $request->user()->incomeSources()
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();

        return $this->ok(IncomeSourceResource::collection($sources));
    }

    public function store(StoreIncomeSourceRequest $request): JsonResponse
    {
        $user = $request->user();
        $verdict = $this->gate->canAddIncomeSource($user);

        if (! $verdict['allowed']) {
            throw new PlanLimitException($verdict['reason'], $verdict['limit'], $verdict['used'], 'income_sources');
        }

        $data = $request->validated();

        $source = DB::transaction(function () use ($user, $data) {
            $source = $user->incomeSources()->create($data);

            if (! empty($data['is_primary'])) {
                $this->demoteOtherPrimaries($user, $source->id);
            }

            // The first source becomes primary; a new primary is mirrored onto the user.
            $user->ensurePrimaryIncome();

            return $source;
        });

        return $this->created(new IncomeSourceResource($source->fresh()), 'Income saved successfully.');
    }

    public function show(Request $request, IncomeSource $incomeSource): JsonResponse
    {
        $this->authorize('view', $incomeSource);

        return $this->ok(new IncomeSourceResource($incomeSource));
    }

    public function update(UpdateIncomeSourceRequest $request, IncomeSource $incomeSource): JsonResponse
    {
        $this->authorize('update', $incomeSource);

        $data = $request->validated();

        if (filter_var($data['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN) && ! $incomeSource->is_active) {
            $this->gate->ensureCanReactivate($request->user(), 'income_sources');
            $data += ['archived_at' => null, 'archived_reason' => null];
        }

        $makePrimary = filter_var($data['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $unsetPrimary = array_key_exists('is_primary', $data) && ! $makePrimary && $incomeSource->is_primary;

        DB::transaction(function () use ($request, $incomeSource, $data, $makePrimary, $unsetPrimary) {
            $incomeSource->update($data);

            if ($makePrimary) {
                $this->demoteOtherPrimaries($request->user(), $incomeSource->id);
            }

            // Un-setting, pausing or editing the primary: hand the role to the next
            // active source if needed and keep the user's pay schedule in sync.
            $request->user()->ensurePrimaryIncome($unsetPrimary ? $incomeSource->id : null);
        });

        return $this->ok(new IncomeSourceResource($incomeSource->fresh()), 'Income updated successfully.');
    }

    public function destroy(Request $request, IncomeSource $incomeSource): JsonResponse
    {
        $this->authorize('delete', $incomeSource);

        DB::transaction(function () use ($request, $incomeSource) {
            $incomeSource->delete();

            // Deleting the primary promotes the next active source.
            $request->user()->ensurePrimaryIncome();
        });

        return $this->ok(null, 'Income source removed.');
    }

    private function demoteOtherPrimaries($user, int $keepId): void
    {
        $user->incomeSources()
            ->whereKeyNot($keepId)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);
    }
}
