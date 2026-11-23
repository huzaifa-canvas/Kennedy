<?php

namespace App\Http\Requests\Api;

/**
 * The items a downgraded user keeps active on Free. Limits and ownership are
 * checked in PlanDowngrade::applySelection, against the user's own items.
 */
class PlanSelectionRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'bills' => ['present', 'array', 'max:100'],
            'bills.*' => ['integer', 'distinct'],
            'income_sources' => ['present', 'array', 'max:100'],
            'income_sources.*' => ['integer', 'distinct'],
            'savings_jars' => ['present', 'array', 'max:100'],
            'savings_jars.*' => ['integer', 'distinct'],
        ];
    }
}
