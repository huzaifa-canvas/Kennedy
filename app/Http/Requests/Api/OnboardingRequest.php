<?php

namespace App\Http\Requests\Api;

use App\Models\Bill;
use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * The three setup screens submitted together, or one step at a time —
 * every section is optional so the app can post as the user progresses.
 */
class OnboardingRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'pay_frequency' => ['sometimes', 'required', Rule::in(User::PAY_FREQUENCIES)],
            'next_payday' => ['sometimes', 'nullable', 'date', 'after_or_equal:2000-01-01', 'before:+5 years'],
            'estimated_amount' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:9999999.99'],

            'bills' => ['sometimes', 'array', 'max:20'],
            'bills.*.name' => ['required_with:bills', 'string', 'min:2', 'max:100'],
            'bills.*.amount' => ['required_with:bills', 'numeric', 'min:0.01', 'max:9999999.99'],
            'bills.*.due_date' => ['required_with:bills', 'date', 'after_or_equal:2000-01-01', 'before:+10 years'],
            'bills.*.recurrence' => ['required_with:bills', Rule::in(Bill::RECURRENCES)],
            'bills.*.bill_category_id' => ['nullable', 'integer', Rule::exists('bill_categories', 'id')->where('is_active', true)],

            'complete' => ['sometimes', 'boolean'],
        ];
    }
}
