@extends('layouts/layoutMaster')

@section('title', 'Subscriptions')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Monetisation /</span> Subscriptions
</h4>

<div class="row g-4 mb-4">
  @foreach([
    ['label' => 'Active', 'value' => $counts['active'], 'tone' => 'success'],
    ['label' => 'Trialing', 'value' => $counts['trialing'], 'tone' => 'info'],
    ['label' => 'Cancelled', 'value' => $counts['cancelled'], 'tone' => 'warning'],
    ['label' => 'Expired', 'value' => $counts['expired'], 'tone' => 'secondary'],
  ] as $card)
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body">
          <p class="mb-1 text-muted">{{ $card['label'] }}</p>
          <h4 class="mb-0 text-{{ $card['tone'] }}">{{ number_format($card['value']) }}</h4>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Grant a Plan</h5>
        <small class="text-muted">For comps and support fixes. Bypasses the store.</small>
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
          <div class="col-md-6">
            <input type="text" name="search" class="form-control" placeholder="Search user"
                   value="{{ $filters['search'] ?? '' }}" maxlength="100" />
          </div>
          <div class="col-md-3">
            <select name="status" class="form-select">
              <option value="">Any status</option>
              @foreach(['active', 'trialing', 'cancelled', 'expired'] as $status)
                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
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
              <th>User</th><th>Plan</th><th>Status</th><th>Source</th><th>Ends</th><th class="text-end">Actions</th>
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
                <td>{{ $subscription->plan?->name ?? '—' }}</td>
                <td>
                  @php
                    $tone = match ($subscription->status) {
                      'active' => 'success', 'trialing' => 'info',
                      'cancelled' => 'warning', default => 'secondary',
                    };
                  @endphp
                  <span class="badge bg-label-{{ $tone }}">{{ ucfirst($subscription->status) }}</span>
                </td>
                <td class="text-muted">{{ ucfirst($subscription->provider) }}</td>
                <td class="text-muted">{{ $subscription->ends_at?->format('d M Y') ?: '—' }}</td>
                <td class="text-end">
                  @if($subscription->isActive())
                    <form method="POST" action="{{ route('admin-subscription-revoke', $subscription->id) }}">
                      @csrf
                      @method('PATCH')
                      <button type="submit" class="btn btn-sm btn-label-danger"
                              onclick="return confirm('Revoke this subscription immediately?')">Revoke</button>
                    </form>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-center py-4 text-muted">No subscriptions yet.</td></tr>
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
