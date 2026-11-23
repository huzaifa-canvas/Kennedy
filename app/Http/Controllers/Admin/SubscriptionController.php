<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\RevenueCatClient;
use App\Services\SubscriptionManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Store subscriptions (App Store / Google Play via RevenueCat) are read-only
 * here: billing, refunds and cancellations happen in the stores, and
 * RevenueCat would overwrite any local change. Admins can re-sync them from
 * RevenueCat, and grant / revoke manual (comp) access.
 */
class SubscriptionController extends Controller
{
    public const FILTERS = ['trialing', 'active', 'canceled', 'billing_issue', 'expired'];

    public function __construct(
        private readonly SubscriptionManager $subscriptions,
        private readonly RevenueCatClient $revenueCat,
    ) {
    }

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(self::FILTERS)],
            'store' => ['nullable', 'in:app_store,play_store,manual'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $query = Subscription::with(['user:id,name,email', 'plan:id,name,slug,price,billing_period']);

        if (! empty($filters['status'])) {
            $this->applyStatusFilter($query, $filters['status']);
        }

        if (! empty($filters['store'])) {
            $filters['store'] === 'manual'
                ? $query->where('provider', 'manual')
                : $query->where('store', $filters['store']);
        }

        if (! empty($filters['search'])) {
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';
            $query->whereHas('user', fn ($u) => $u->where('email', 'like', $term)->orWhere('name', 'like', $term));
        }

        $subscriptions = $query->latest()->paginate(20)->withQueryString();

        $counts = [];
        foreach (self::FILTERS as $status) {
            $counts[$status] = $this->applyStatusFilter(Subscription::query(), $status)->count();
        }

        $counts['awaiting_selection'] = User::whereNotNull('plan_selection_required_at')->count();

        $plans = Plan::where('is_active', true)->where('price', '>', 0)->orderBy('sort_order')->get();
        $revenueCatConfigured = $this->revenueCat->isConfigured();

        return view('content.admin.subscriptions.index', compact(
            'subscriptions', 'counts', 'plans', 'filters', 'revenueCatConfigured'
        ));
    }

    /** Hand a user Pro without going through the store — comps and support fixes. */
    public function grant(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc', 'exists:users,email'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')->where('is_active', true)],
            'ends_at' => ['nullable', 'date', 'after:today', 'before:+10 years'],
        ]);

        $user = User::where('email', strtolower($data['email']))->firstOrFail();
        $wasPro = $user->isPro();

        // Replace an earlier comp; store subscriptions are left to RevenueCat.
        $user->subscriptions()
            ->where('provider', 'manual')
            ->whereIn('status', Subscription::LIVE_STATUSES)
            ->update(['status' => 'expired', 'will_renew' => false, 'ends_at' => now()]);

        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at'])->endOfDay() : null;

        $user->subscriptions()->create([
            'plan_id' => $data['plan_id'],
            'status' => 'active',
            'provider' => 'manual',
            'store' => 'manual',
            'provider_subscription_id' => 'manual-' . $user->id . '-' . now()->timestamp,
            'will_renew' => false,
            'started_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $this->subscriptions->settle($user, $wasPro);

        return back()->with('success', "Pro granted to {$user->email}. If they had archived data, they can now restore it in the app.");
    }

    /** End a manual grant now. The user drops to Free unless a store subscription remains. */
    public function revoke(Subscription $subscription): RedirectResponse
    {
        if ($subscription->provider !== 'manual') {
            return back()->with('error', 'Store subscriptions are managed by Apple / Google. Refund or cancel them in App Store Connect or Google Play Console, then re-sync.');
        }

        $this->subscriptions->expire($subscription);

        return back()->with('success', 'Manual subscription revoked.');
    }

    /** Pull the user's current state from RevenueCat (support: "I paid but I'm not Pro"). */
    public function resync(User $user): RedirectResponse
    {
        if (! $this->revenueCat->isConfigured()) {
            return back()->with('error', 'Set REVENUECAT_SECRET_API_KEY to re-sync from RevenueCat.');
        }

        if (! $this->subscriptions->refreshFromRevenueCat($user)) {
            return back()->with('error', 'RevenueCat did not respond. Try again shortly.');
        }

        return back()->with('success', "Subscription re-synced from RevenueCat for {$user->email}.");
    }

    private function applyStatusFilter(Builder $query, string $status): Builder
    {
        return match ($status) {
            'trialing' => $query->active()->where('status', 'trialing')->where('will_renew', true),
            'active' => $query->active()->where('status', 'active')->where('will_renew', true)->whereNull('billing_issue_detected_at'),
            'canceled' => $query->active()->where(fn ($q) => $q->where('status', 'cancelled')->orWhere('will_renew', false)),
            'billing_issue' => $query->active()->whereNotNull('billing_issue_detected_at'),
            'expired' => $query->where(fn ($q) => $q->where('status', 'expired')
                ->orWhere(fn ($q) => $q->whereIn('status', Subscription::LIVE_STATUSES)->accessEndedBefore(now()))),
        };
    }
}
