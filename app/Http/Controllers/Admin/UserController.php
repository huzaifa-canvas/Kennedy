<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\SubscriptionManager;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    /** Only these columns may ever reach an ORDER BY. */
    private const SORTABLE = ['name', 'email', 'created_at', 'last_login_at'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,suspended'],
            'role' => ['nullable', 'in:user,admin'],
            'plan' => ['nullable', 'string', 'exists:plans,slug'],
            'sort_by' => ['nullable', 'in:' . implode(',', self::SORTABLE)],
            'sort_dir' => ['nullable', 'in:asc,desc'],
        ]);

        $query = User::query()->with('activeSubscription.plan');

        if (! empty($filters['search'])) {
            // Escaped and bound: the wildcards go in the value, not the SQL.
            $term = '%' . str_replace(['%', '_'], ['\%', '\_'], $filters['search']) . '%';

            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term);
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (! empty($filters['plan'])) {
            $slug = $filters['plan'];

            if ($slug === 'free') {
                $query->whereDoesntHave('subscriptions', fn ($q) => $q->active());
            } else {
                $query->whereHas('subscriptions', function ($q) use ($slug) {
                    $q->active()->whereHas('plan', fn ($p) => $p->where('slug', $slug));
                });
            }
        }

        $users = $query
            ->orderBy($filters['sort_by'] ?? 'created_at', $filters['sort_dir'] ?? 'desc')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'total' => User::customers()->count(),
            'active' => User::customers()->where('status', 'active')->count(),
            'suspended' => User::customers()->where('status', 'suspended')->count(),
            'admins' => User::admins()->count(),
        ];

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get();

        return view('content.admin.users.index', compact('users', 'counts', 'plans', 'filters'));
    }

    public function show(User $user): View
    {
        $user->load([
            'incomeSources',
            'bills.category',
            'savingsJars',
            'activeSubscription.plan',
        ]);

        $recentTransactions = $user->transactions()
            ->with('category')
            ->orderByDesc('occurred_on')
            ->limit(15)
            ->get();

        $notifications = $user->notifications()->limit(10)->get();

        $subscription = app(SubscriptionManager::class)->summary($user);

        return view('content.admin.users.show', compact('user', 'recentTransactions', 'notifications', 'subscription'));
    }

    public function create(): View
    {
        return view('content.admin.users.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(['user', 'admin'])],
            'password' => ['required', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => 'active',
            'password' => Hash::make($data['password']),
        ]);


        return redirect()->route('app-user-list')->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('content.admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:32'],
            'role' => ['required', Rule::in(['user', 'admin'])],
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'password' => ['nullable', 'string', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // An admin must not lock themselves out.
        if ($user->id === auth()->id() && ($data['role'] !== 'admin' || $data['status'] !== 'active')) {
            return back()->with('error', 'You cannot remove your own admin access.');
        }

        $user->fill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => $data['status'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
            $user->tokens()->delete();
        }

        $user->save();

        if ($data['status'] === 'suspended') {
            $user->tokens()->delete();
        }


        return redirect()->route('app-user-list')->with('success', 'User updated successfully.');
    }

    public function toggleStatus(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->status = $user->status === 'active' ? 'suspended' : 'active';
        $user->save();

        if ($user->status === 'suspended') {
            $user->tokens()->delete();
        }


        return back()->with('success', "User {$user->status}.");
    }

    public function destroy(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->notifications()->delete();
            $user->devices()->delete();
            $user->delete();
        });


        return back()->with('success', 'User deleted successfully.');
    }
}
