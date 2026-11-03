<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::withCount(['subscriptions as active_subscriptions_count' => fn ($q) => $q->active()])
            ->orderBy('sort_order')
            ->get();

        return view('content.admin.plans.index', compact('plans'));
    }

    public function edit(Plan $plan): View
    {
        return view('content.admin.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999.99'],
            'billing_period' => ['required', 'in:monthly,yearly,lifetime'],
            'store_product_id' => ['nullable', 'string', 'max:191', 'regex:/^[A-Za-z0-9_\.\-]*$/'],
            'bill_limit' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'income_source_limit' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'savings_jar_limit' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'history_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'allows_smart_splits' => ['nullable', 'boolean'],
            'allows_custom_reminders' => ['nullable', 'boolean'],
            'allows_advanced_recurrence' => ['nullable', 'boolean'],
            'allows_calendar_themes' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'features' => ['nullable', 'array', 'max:30'],
            'features.*.label' => ['required_with:features', 'string', 'max:120'],
            'features.*.included' => ['nullable', 'boolean'],
        ]);

        // Checkboxes that are off are simply absent from the payload.
        foreach ([
            'allows_smart_splits', 'allows_custom_reminders',
            'allows_advanced_recurrence', 'allows_calendar_themes', 'is_active',
        ] as $flag) {
            $data[$flag] = (bool) ($data[$flag] ?? false);
        }

        if (isset($data['features'])) {
            $data['features'] = array_values(array_map(fn ($row) => [
                'label' => $row['label'],
                'included' => (bool) ($row['included'] ?? false),
            ], $data['features']));
        }

        $plan->update($data);


        return redirect()->route('admin-plans')->with('success', 'Plan updated successfully.');
    }
}
