<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SubscriptionController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'in:trialing,active,cancelled,expired'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Subscription::with(['user:id,name,email', 'plan:id,name,slug,price']);

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';
            $query->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
        }

        $subscriptions = $query->latest()->paginate(20)->withQueryString();

        $counts = [
            'active' => Subscription::active()->count(),
            'trialing' => Subscription::where('status', 'trialing')->count(),
            'cancelled' => Subscription::where('status', 'cancelled')->count(),
            'expired' => Subscription::where('status', 'expired')->count(),
        ];

        $plans = Plan::where('is_active', true)->where('price', '>', 0)->orderBy('sort_order')->get();

        return view('content.admin.subscriptions.index', compact('subscriptions', 'counts', 'plans', 'filters'));
    }

    /** Hand a user a plan without going through the store — comps and support fixes. */
    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'exists:users,email'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'ends_at' => ['nullable', 'date', 'after:today', 'before:+10 years'],
        ]);

        $user = User::where('email', strtolower($data['email']))->firstOrFail();

        $user->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $subscription = $user->subscriptions()->create([
            'plan_id' => $data['plan_id'],
            'status' => 'active',
            'provider' => 'manual',
            'provider_subscription_id' => 'manual-' . $user->id . '-' . now()->timestamp,
            'started_at' => now(),
            'ends_at' => isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null,
        ]);


        return back()->with('success', "Subscription granted to {$user->email}.");
    }

    public function revoke(Subscription $subscription): RedirectResponse
    {
        $subscription->update([
            'status' => 'expired',
            'ends_at' => now(),
            'cancelled_at' => now(),
        ]);


        return back()->with('success', 'Subscription revoked.');
    }
}
