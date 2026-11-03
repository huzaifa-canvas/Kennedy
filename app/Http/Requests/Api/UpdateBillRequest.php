<?php

namespace App\Http\Requests\Api;

use App\Models\Bill;
use Illuminate\Validation\Rule;

class UpdateBillRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'amount' => ['sometimes', 'required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'due_date' => ['sometimes', 'required', 'date', 'after_or_equal:2000-01-01', 'before:+10 years'],
            'bill_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('bill_categories', 'id')->where('is_active', true)],
            'recurrence' => ['sometimes', 'required', Rule::in(Bill::RECURRENCES)],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reminder_days_before' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
