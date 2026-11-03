@extends('layouts/layoutMaster')

@section('title', 'Dashboard')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Saverr /</span> Overview
</h4>

{{-- KPI cards --}}
<div class="row g-4 mb-4">
  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-primary h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-primary"><i class="icon-base ti tabler-users"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['total_users']) }}</h4>
        </div>
        <p class="mb-1">Total Users</p>
        <p class="mb-0 text-muted small">
          <span class="fw-medium text-success">+{{ $stats['new_users_this_month'] }}</span> this month
        </p>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-warning h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-warning"><i class="icon-base ti tabler-crown"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['active_subscriptions']) }}</h4>
        </div>
        <p class="mb-1">Active Subscriptions</p>
        <p class="mb-0 text-muted small">
          <span class="fw-medium">${{ number_format($stats['mrr'], 2) }}</span> monthly recurring
        </p>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-info h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-info"><i class="icon-base ti tabler-receipt"></i></span>
          </div>
          <h4 class="ms-1 mb-0">{{ number_format($stats['total_bills']) }}</h4>
        </div>
        <p class="mb-1">Bills Tracked</p>
        <p class="mb-0 text-muted small">
          <span class="fw-medium">${{ number_format($stats['bills_due_this_month'], 2) }}</span> due this month
        </p>
      </div>
    </div>
  </div>

  <div class="col-sm-6 col-lg-3">
    <div class="card card-border-shadow-success h-100">
      <div class="card-body">
        <div class="d-flex align-items-center mb-2 pb-1">
          <div class="avatar me-2">
            <span class="avatar-initial rounded bg-label-success"><i class="icon-base ti tabler-pig-money"></i></span>
          </div>
          <h4 class="ms-1 mb-0">${{ number_format($stats['total_saved'], 2) }}</h4>
        </div>
        <p class="mb-1">Saved In Jars</p>
        <p class="mb-0 text-muted small">
          across <span class="fw-medium">{{ $stats['savings_jars'] }}</span> jars
        </p>
      </div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  {{-- Signups --}}
  <div class="col-lg-8">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <div>
          <h5 class="mb-0">Signups</h5>
          <small class="text-muted">Last 12 months</small>
        </div>
        <span class="badge bg-label-primary">{{ $months->sum('count') }} total</span>
      </div>
      <div class="card-body">
        @php $peak = max(1, $months->max('count')); @endphp
        <div class="d-flex align-items-end justify-content-between gap-2" style="height: 220px;">
          @foreach($months as $month)
            <div class="d-flex flex-column align-items-center flex-fill h-100 justify-content-end">
              <span class="small fw-medium mb-1">{{ $month['count'] ?: '' }}</span>
              <div class="w-100 rounded-top bg-primary"
                   style="height: {{ max(2, (int) round(($month['count'] / $peak) * 160)) }}px; opacity: {{ $month['count'] ? 1 : .25 }};"
                   title="{{ $month['key'] }}: {{ $month['count'] }}"></div>
              <small class="text-muted mt-2">{{ $month['label'] }}</small>
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </div>

  {{-- Plan split --}}
  <div class="col-lg-4">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Plan Distribution</h5>
      </div>
      <div class="card-body">
        @php $totalPlanned = max(1, $planBreakdown->sum()); @endphp
        @forelse($planBreakdown as $planName => $count)
          <div class="mb-4">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-medium">{{ $planName }}</span>
              <span class="text-muted">{{ $count }} ({{ (int) round(($count / $totalPlanned) * 100) }}%)</span>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar {{ str_contains(strtolower($planName), 'pro') ? 'bg-warning' : 'bg-primary' }}"
                   style="width: {{ (int) round(($count / $totalPlanned) * 100) }}%"></div>
            </div>
          </div>
        @empty
          <p class="text-muted mb-0">No users yet.</p>
        @endforelse

        <hr>
        <div class="d-flex justify-content-between">
          <span class="text-muted">Suspended accounts</span>
          <span class="fw-medium text-danger">{{ $stats['suspended_users'] }}</span>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  {{-- Recent users --}}
  <div class="col-lg-7">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Signups</h5>
        <a href="{{ route('app-user-list') }}" class="btn btn-sm btn-label-primary">View All</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover">
          <thead class="table-light">
            <tr>
              <th>User</th>
              <th>Plan</th>
              <th>Joined</th>
            </tr>
          </thead>
          <tbody>
            @forelse($recentUsers as $user)
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <div class="avatar avatar-sm me-3">
                      <img src="{{ $user->avatar_url }}" alt="Avatar" class="rounded-circle" />
                    </div>
                    <div>
                      <a href="{{ route('app-user-view', $user->id) }}" class="fw-medium d-block">{{ $user->name }}</a>
                      <small class="text-muted">{{ $user->email }}</small>
                    </div>
                  </div>
                </td>
                <td>
                  @php $plan = $user->plan(); @endphp
                  <span class="badge {{ $plan->isFree() ? 'bg-label-secondary' : 'bg-label-warning' }}">{{ $plan->name }}</span>
                </td>
                <td class="text-muted">{{ $user->created_at->format('d M Y') }}</td>
              </tr>
            @empty
              <tr><td colspan="3" class="text-center py-4 text-muted">No users yet.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>

  {{-- Spend by category --}}
  <div class="col-lg-5">
    <div class="card h-100">
      <div class="card-header">
        <h5 class="mb-0">Bills By Category</h5>
        <small class="text-muted">Due this month</small>
      </div>
      <div class="card-body">
        @php $catPeak = max(1, (float) $topCategories->max('total')); @endphp
        @forelse($topCategories as $category)
          <div class="mb-4">
            <div class="d-flex justify-content-between mb-1">
              <span class="fw-medium">{{ $category->name }}</span>
              <span class="text-muted">${{ number_format((float) $category->total, 2) }}</span>
            </div>
            <div class="progress" style="height: 8px;">
              <div class="progress-bar"
                   style="width: {{ (int) round(((float) $category->total / $catPeak) * 100) }}%; background-color: {{ $category->color ?? '#64748B' }};"></div>
            </div>
          </div>
        @empty
          <p class="text-muted mb-0">No bills due this month.</p>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
