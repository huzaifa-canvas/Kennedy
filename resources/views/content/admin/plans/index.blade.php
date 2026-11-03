@extends('layouts/layoutMaster')

@section('title', 'Plans')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Monetisation /</span> Plans
</h4>

<div class="row g-4">
  @foreach($plans as $plan)
    <div class="col-lg-6">
      <div class="card h-100 {{ $plan->isFree() ? '' : 'border border-warning' }}">
        <div class="card-header d-flex justify-content-between align-items-start">
          <div>
            <h5 class="mb-1">
              @if(! $plan->isFree())<i class="icon-base ti tabler-crown text-warning me-1"></i>@endif
              {{ $plan->name }}
            </h5>
            <small class="text-muted">{{ $plan->tagline }}</small>
          </div>
          <span class="badge {{ $plan->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
            {{ $plan->is_active ? 'Active' : 'Hidden' }}
          </span>
        </div>
        <div class="card-body">
          <h3 class="mb-4">
            ${{ number_format((float) $plan->price, 2) }}
            <small class="text-muted fs-6">/{{ $plan->billing_period }}</small>
          </h3>

          <div class="row g-3 mb-4">
            <div class="col-6">
              <small class="text-muted d-block">Bill limit</small>
              <span class="fw-medium">{{ $plan->bill_limit ?? 'Unlimited' }}</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Savings jars</small>
              <span class="fw-medium">{{ $plan->savings_jar_limit ?? 'Unlimited' }}</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">Income sources</small>
              <span class="fw-medium">{{ $plan->income_source_limit ?? 'Unlimited' }}</span>
            </div>
            <div class="col-6">
              <small class="text-muted d-block">History window</small>
              <span class="fw-medium">{{ $plan->history_days ? $plan->history_days . ' days' : 'Unlimited' }}</span>
            </div>
          </div>

          <div class="d-flex flex-wrap gap-2 mb-4">
            @foreach([
              'Smart splits' => $plan->allows_smart_splits,
              'Custom reminders' => $plan->allows_custom_reminders,
              'Weekly/bi-weekly bills' => $plan->allows_advanced_recurrence,
              'Calendar themes' => $plan->allows_calendar_themes,
            ] as $label => $enabled)
              <span class="badge {{ $enabled ? 'bg-label-success' : 'bg-label-secondary' }}">
                <i class="icon-base ti {{ $enabled ? 'tabler-check' : 'tabler-x' }} me-1"></i>{{ $label }}
              </span>
            @endforeach
          </div>

          <div class="d-flex justify-content-between align-items-center">
            <span class="text-muted">
              <i class="icon-base ti tabler-users me-1"></i>
              {{ $plan->active_subscriptions_count }} active
            </span>
            <a href="{{ route('admin-plan-edit', $plan->id) }}" class="btn btn-primary">
              <i class="icon-base ti tabler-edit me-2"></i>Edit Plan
            </a>
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>
@endsection
