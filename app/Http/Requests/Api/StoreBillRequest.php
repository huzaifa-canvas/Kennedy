<?php

namespace App\Http\Requests\Api;

use App\Models\Bill;
use Illuminate\Validation\Rule;

class StoreBillRequest extends ApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'due_date' => ['required', 'date', 'after_or_equal:2000-01-01', 'before:+10 years'],
            'bill_category_id' => ['nullable', 'integer', Rule::exists('bill_categories', 'id')->where('is_active', true)],
            'recurrence' => ['required', Rule::in(Bill::RECURRENCES)],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'reminder_days_before' => ['nullable', 'integer', 'min:0', 'max:30'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->name)) {
            $this->merge(['name' => trim($this->name)]);
        }
    }
}
