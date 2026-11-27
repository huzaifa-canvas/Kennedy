@extends('layouts/layoutMaster')

@section('title', $user->name . ' - User')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"><a href="{{ route('app-user-list') }}">Users</a> /</span> {{ $user->name }}
</h4>

<div class="row g-4">
  {{-- Profile card --}}
  <div class="col-xl-4">
    <div class="card mb-4">
      <div class="card-body text-center">
        <div class="avatar avatar-xl mb-3">
          <img src="{{ $user->avatar_url }}" alt="Avatar" class="rounded-circle" />
        </div>
        <h5 class="mb-1">{{ $user->name }}</h5>
        <p class="text-muted mb-2">{{ $user->email }}</p>
        <span class="badge {{ $user->isActive() ? 'bg-label-success' : 'bg-label-danger' }}">{{ ucfirst($user->status) }}</span>
        @php $plan = $user->plan(); @endphp
        <span class="badge {{ $plan->isFree() ? 'bg-label-secondary' : 'bg-label-warning' }}">{{ $plan->name }}</span>

        <hr>
        <dl class="row mb-0 text-start">
          <dt class="col-6 fw-normal text-muted">Phone</dt>
          <dd class="col-6">{{ $user->phone ?: '—' }}</dd>
          <dt class="col-6 fw-normal text-muted">Pay frequency</dt>
          <dd class="col-6">{{ \App\Models\IncomeSource::frequencyLabel($user->pay_frequency) ?? '—' }}</dd>
          <dt class="col-6 fw-normal text-muted">Next payday</dt>
          <dd class="col-6">{{ $user->next_payday?->format('d M Y') ?: '—' }}</dd>
          <dt class="col-6 fw-normal text-muted">Currency</dt>
          <dd class="col-6">{{ $user->currency }}</dd>
          <dt class="col-6 fw-normal text-muted">Notifications</dt>
          <dd class="col-6">{{ $user->notifications_enabled ? 'On' : 'Off' }}</dd>
          <dt class="col-6 fw-normal text-muted">Onboarding</dt>
          <dd class="col-6">{{ $user->onboarding_completed_at ? 'Complete' : 'Incomplete' }}</dd>
          <dt class="col-6 fw-normal text-muted">Member since</dt>
          <dd class="col-6">{{ $user->created_at->format('M Y') }}</dd>
          <dt class="col-6 fw-normal text-muted">Last login</dt>
          <dd class="col-6">{{ $user->last_login_at?->diffForHumans() ?: 'Never' }}</dd>
        </dl>

        <a href="{{ route('app-user-edit', $user->id) }}" class="btn btn-primary w-100 mt-4">
          <i class="icon-base ti tabler-edit me-2"></i>Edit User
        </a>
      </div>
    </div>

    @php
      $statusTone = match ($subscription['status']) {
        'active' => 'success', 'free_trial' => 'info', 'canceled' => 'warning',
        'expired' => 'danger', default => 'secondary',
      };
      $fmt = fn ($iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->format('d M Y') : '—';
    @endphp
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Subscription</h5>
        <span class="badge bg-label-{{ $statusTone }}">{{ $subscription['status_label'] }}</span>
      </div>
      <div class="card-body">
        @if($subscription['requires_plan_selection'])
          <div class="alert alert-warning py-2 mb-3">
            Downgraded to Free with more data than Free allows. Waiting for the user to choose what stays active.
          </div>
        @endif
        @if($subscription['billing_issue'])
          <div class="alert alert-danger py-2 mb-3">
            Payment failed. The store is retrying; Pro is kept
            @if($subscription['grace_period_ends_at']) until {{ $fmt($subscription['grace_period_ends_at']) }} @endif.
          </div>
        @endif
        <dl class="row mb-0">
          <dt class="col-5 fw-normal text-muted">Plan</dt>
          <dd class="col-7">{{ $subscription['plan_name'] }}</dd>
          @if($subscription['is_pro'])
            <dt class="col-5 fw-normal text-muted">Price</dt>
            <dd class="col-7">${{ number_format((float) $subscription['price'], 2) }} / {{ $subscription['billing_period'] === 'yearly' ? 'year' : 'month' }}</dd>
          @endif
          <dt class="col-5 fw-normal text-muted">Store</dt>
          <dd class="col-7">{{ match ($subscription['store']) { 'app_store' => 'Apple App Store', 'play_store' => 'Google Play', 'manual' => 'Manual (admin)', null => '—', default => ucfirst($subscription['store']) } }}</dd>
          @if($subscription['trial_started_at'])
            <dt class="col-5 fw-normal text-muted">Trial</dt>
            <dd class="col-7">{{ $fmt($subscription['trial_started_at']) }} → {{ $fmt($subscription['trial_ends_at']) }}</dd>
          @endif
          <dt class="col-5 fw-normal text-muted">Auto-renew</dt>
          <dd class="col-7">{{ $subscription['auto_renew'] ? 'On' : 'Off' }}</dd>
          <dt class="col-5 fw-normal text-muted">Next billing</dt>
          <dd class="col-7">{{ $fmt($subscription['next_billing_date']) }}</dd>
          @if($subscription['access_ends_at'])
            <dt class="col-5 fw-normal text-muted">Pro ends</dt>
            <dd class="col-7">{{ $fmt($subscription['access_ends_at']) }}</dd>
          @endif
          @if($subscription['expired_at'])
            <dt class="col-5 fw-normal text-muted">Expired</dt>
            <dd class="col-7">{{ $fmt($subscription['expired_at']) }}</dd>
          @endif
        </dl>

        <form method="POST" action="{{ route('admin-subscription-resync', $user->id) }}" class="mt-3">
          @csrf
          <button type="submit" class="btn btn-sm btn-label-primary w-100">
            <i class="icon-base ti tabler-refresh me-1"></i>Re-sync from RevenueCat
          </button>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    {{-- Income --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Income Sources ({{ $user->incomeSources->count() }})</h5></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr><th>Source</th><th>Amount</th><th>Frequency</th><th>Next Payday</th></tr>
          </thead>
          <tbody>
            @forelse($user->incomeSources as $source)
              <tr>
                <td class="fw-medium">{{ $source->name }} @if($source->is_primary)<span class="badge bg-label-primary ms-1">Primary</span>@endif @include('content.admin.users._archived-badge', ['item' => $source, 'active' => $source->is_active])</td>
                <td>${{ number_format((float) $source->amount, 2) }}</td>
                <td class="text-muted">{{ \App\Models\IncomeSource::frequencyLabel($source->pay_frequency) }}</td>
                <td class="text-muted">{{ $source->next_payday?->format('d M Y') ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center py-3 text-muted">No income sources.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Bills --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Bills ({{ $user->bills->count() }})</h5></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr><th>Bill</th><th>Category</th><th>Amount</th><th>Recurrence</th><th>Next Due</th></tr>
          </thead>
          <tbody>
            @forelse($user->bills as $bill)
              <tr>
                <td class="fw-medium">
                  <a href="{{ route('admin-bill-view', $bill->id) }}">{{ $bill->name }}</a>
                  @include('content.admin.users._archived-badge', ['item' => $bill, 'active' => $bill->is_active])
                </td>
                <td>
                  @if($bill->category)
                    <span class="badge" style="background-color: {{ $bill->category->color }}1a; color: {{ $bill->category->color }};">
                      {{ $bill->category->name }}
                    </span>
                  @else
                    <span class="text-muted">—</span>
                  @endif
                </td>
                <td>${{ number_format((float) $bill->amount, 2) }}</td>
                <td class="text-muted">{{ str_replace('_', ' ', ucfirst($bill->recurrence)) }}</td>
                <td class="text-muted">{{ $bill->next_due_date?->format('d M Y') ?: '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="5" class="text-center py-3 text-muted">No bills.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Savings jars --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Savings Jars ({{ $user->savingsJars->count() }})</h5></div>
      <div class="card-body">
        @forelse($user->savingsJars as $jar)
          <div class="mb-4">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-medium">{{ $jar->name }} @include('content.admin.users._archived-badge', ['item' => $jar, 'active' => ! $jar->is_archived])</span>
              <span class="text-muted">
                ${{ number_format((float) $jar->saved_amount, 2) }} of ${{ number_format((float) $jar->target_amount, 2) }}
                ({{ $jar->progress_percent }}%)
              </span>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar" style="width: {{ $jar->progress_percent }}%; background-color: {{ $jar->color }};"></div>
            </div>
          </div>
        @empty
          <p class="text-muted mb-0">No savings jars.</p>
        @endforelse
      </div>
    </div>

    {{-- Transactions --}}
    <div class="card mb-4">
      <div class="card-header"><h5 class="mb-0">Recent Transactions</h5></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr><th>Date</th><th>Description</th><th class="text-end">Amount</th></tr>
          </thead>
          <tbody>
            @forelse($recentTransactions as $transaction)
              <tr>
                <td class="text-muted">{{ $transaction->occurred_on->format('d M Y') }}</td>
                <td class="fw-medium">{{ $transaction->title }}</td>
                <td class="text-end {{ $transaction->type === 'income' ? 'text-success' : '' }}">
                  {{ $transaction->type === 'income' ? '+' : '-' }}${{ number_format((float) $transaction->amount, 2) }}
                </td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center py-3 text-muted">No transactions.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- Notifications --}}
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Recent Notifications</h5></div>
      <div class="card-body">
        @forelse($notifications as $notification)
          @php $data = $notification->data ?? []; @endphp
          <div class="d-flex justify-content-between border-bottom py-2">
            <div>
              <span class="{{ $notification->read_at ? 'text-muted' : 'fw-medium' }}">{{ $data['title'] ?? '—' }}</span>
              <small class="d-block text-muted">{{ $data['type'] ?? 'system' }}</small>
            </div>
            <small class="text-muted text-nowrap ms-3">{{ $notification->created_at->diffForHumans() }}</small>
          </div>
        @empty
          <p class="text-muted mb-0">No notifications.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
