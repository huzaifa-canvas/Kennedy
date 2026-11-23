@extends('layouts/layoutMaster')

@section('title', 'Subscriptions')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Monetisation /</span> Subscriptions
</h4>

@unless($revenueCatConfigured)
  <div class="alert alert-warning mb-4">
    <strong>RevenueCat API key missing.</strong> Set <code>REVENUECAT_SECRET_API_KEY</code> so purchases are verified with RevenueCat
    and the app's <code>subscription/sync</code> call works. Webhooks still apply without it.
  </div>
@endunless

<div class="row g-4 mb-4">
  @foreach([
    ['key' => 'active', 'label' => 'Active (paying)', 'tone' => 'success'],
    ['key' => 'trialing', 'label' => 'Free Trial', 'tone' => 'info'],
    ['key' => 'canceled', 'label' => 'Canceled (access left)', 'tone' => 'warning'],
    ['key' => 'billing_issue', 'label' => 'Billing Issue (grace)', 'tone' => 'danger'],
    ['key' => 'expired', 'label' => 'Expired', 'tone' => 'secondary'],
    ['key' => 'awaiting_selection', 'label' => 'Awaiting Free Selection', 'tone' => 'primary'],
  ] as $card)
    <div class="col-sm-6 col-lg-4 col-xxl-2">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted small">{{ $card['label'] }}</p>
          <h4 class="mb-0 text-{{ $card['tone'] }}">{{ number_format($counts[$card['key']]) }}</h4>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Grant Pro Manually</h5>
        <small class="text-muted">For comps and support fixes. Bypasses the store and is never billed.
          App Store / Google Play subscriptions can only be refunded or cancelled in their consoles.</small>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin-subscription-grant') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="grant-email">User Email</label>
            <input type="email" id="grant-email" name="email" class="form-control" required maxlength="255"
                   value="{{ old('email') }}" />
          </div>
          <div class="mb-3">
            <label class="form-label" for="grant-plan">Plan</label>
            <select id="grant-plan" name="plan_id" class="form-select" required>
              @foreach($plans as $plan)
                <option value="{{ $plan->id }}">{{ $plan->name }} — ${{ number_format((float) $plan->price, 2) }}</option>
              @endforeach
            </select>
          </div>
          <div class="mb-4">
            <label class="form-label" for="grant-ends">Ends On <span class="text-muted">(optional)</span></label>
            <input type="date" id="grant-ends" name="ends_at" class="form-control" value="{{ old('ends_at') }}" />
            <small class="text-muted">Leave blank for no end date.</small>
          </div>
          <button type="submit" class="btn btn-primary w-100">Grant Subscription</button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">All Subscriptions</h5></div>

      <div class="card-body border-bottom">
        <form method="GET" action="{{ route('admin-subscriptions') }}" class="row g-3">
          <div class="col-md-4">
            <input type="text" name="search" class="form-control" placeholder="Search user"
                   value="{{ $filters['search'] ?? '' }}" maxlength="100" />
          </div>
          <div class="col-md-3">
            <select name="status" class="form-select">
              <option value="">Any status</option>
              @foreach(['active' => 'Active', 'trialing' => 'Free Trial', 'canceled' => 'Canceled', 'billing_issue' => 'Billing Issue', 'expired' => 'Expired'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-2">
            <select name="store" class="form-select">
              <option value="">Any store</option>
              @foreach(['app_store' => 'App Store', 'play_store' => 'Google Play', 'manual' => 'Manual'] as $value => $label)
                <option value="{{ $value }}" @selected(($filters['store'] ?? '') === $value)>{{ $label }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-label-primary flex-fill">Filter</button>
            <a href="{{ route('admin-subscriptions') }}" class="btn btn-label-secondary">Reset</a>
          </div>
        </form>
      </div>

      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>User</th><th>Plan</th><th>Status</th><th>Store</th><th>Auto-renew</th><th>Access Until</th><th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($subscriptions as $subscription)
              <tr>
                <td>
                  @if($subscription->user)
                    <a href="{{ route('app-user-view', $subscription->user->id) }}">{{ $subscription->user->name }}</a>
                    <small class="d-block text-muted">{{ $subscription->user->email }}</small>
                  @else
                    <span class="text-muted">Deleted user</span>
                  @endif
                </td>
                <td>
                  {{ $subscription->plan?->name ?? '—' }}
                  @if($subscription->plan && ! $subscription->plan->isFree())
                    <small class="d-block text-muted">${{ number_format((float) $subscription->plan->price, 2) }}/{{ $subscription->plan->billingPeriodLabel() }}</small>
                  @endif
                </td>
                <td>
                  @php
                    $display = $subscription->displayStatus();
                    $tone = match ($display) {
                      'active' => 'success', 'free_trial' => 'info',
                      'canceled' => 'warning', default => 'secondary',
                    };
                  @endphp
                  <span class="badge bg-label-{{ $tone }}">{{ \App\Services\SubscriptionManager::STATUS_LABELS[$display] ?? ucfirst($display) }}</span>
                  @if($subscription->inBillingGrace())
                    <span class="badge bg-label-danger">Billing issue</span>
                  @endif
                  @if($subscription->environment === 'sandbox')
                    <span class="badge bg-label-dark">Sandbox</span>
                  @endif
                </td>
                <td class="text-muted">
                  {{ match ($subscription->store) { 'app_store' => 'App Store', 'play_store' => 'Google Play', 'manual' => 'Manual', null => ucfirst($subscription->provider), default => ucfirst($subscription->store) } }}
                </td>
                <td class="text-muted">{{ $subscription->will_renew && $subscription->isActive() ? 'On' : 'Off' }}</td>
                <td class="text-muted">
                  {{ $subscription->accessEndsAt()?->format('d M Y') ?: ($subscription->isActive() ? 'No end date' : '—') }}
                  @if($subscription->isTrial() && $subscription->trial_ends_at)
                    <small class="d-block">Trial ends {{ $subscription->trial_ends_at->format('d M Y') }}</small>
                  @endif
                </td>
                <td class="text-end">
                  @if($subscription->provider === 'manual' && $subscription->isActive())
                    <form method="POST" action="{{ route('admin-subscription-revoke', $subscription->id) }}">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-sm btn-label-danger"
                              onclick="return confirm('Revoke this manual Pro access now? The user moves to Free unless they also have a store subscription.')">Revoke</button>
                    </form>
                  @elseif($subscription->provider === 'revenuecat' && $subscription->user)
                    <form method="POST" action="{{ route('admin-subscription-resync', $subscription->user->id) }}">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-label-primary">Re-sync</button>
                    </form>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="7" class="text-center py-4 text-muted">No subscriptions yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>

      @if($subscriptions->hasPages())
        <div class="card-footer d-flex justify-content-center">{{ $subscriptions->links() }}</div>
      @endif
    </div>
  </div>
</div>
@endsection
