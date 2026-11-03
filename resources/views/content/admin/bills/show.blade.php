@extends('layouts/layoutMaster')

@section('title', $bill->name . ' - Bill')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"><a href="{{ route('admin-bills') }}">Bills</a> /</span> {{ $bill->name }}
</h4>

<div class="row g-4">
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Bill Details</h5></div>
      <div class="card-body">
        <dl class="row mb-0">
          <dt class="col-5 fw-normal text-muted">Name</dt>
          <dd class="col-7 fw-medium">{{ $bill->name }}</dd>
          <dt class="col-5 fw-normal text-muted">Amount</dt>
          <dd class="col-7">${{ number_format((float) $bill->amount, 2) }}</dd>
          <dt class="col-5 fw-normal text-muted">Category</dt>
          <dd class="col-7">{{ $bill->category?->name ?? '—' }}</dd>
          <dt class="col-5 fw-normal text-muted">Recurrence</dt>
          <dd class="col-7">{{ str_replace('_', '-', $bill->recurrence) }}</dd>
          <dt class="col-5 fw-normal text-muted">First due</dt>
          <dd class="col-7">{{ $bill->due_date->format('d M Y') }}</dd>
          <dt class="col-5 fw-normal text-muted">Next due</dt>
          <dd class="col-7">{{ $bill->next_due_date?->format('d M Y') ?: '—' }}</dd>
          <dt class="col-5 fw-normal text-muted">Reminder</dt>
          <dd class="col-7">{{ $bill->reminder_days_before }} day(s) before</dd>
          <dt class="col-5 fw-normal text-muted">Status</dt>
          <dd class="col-7">
            <span class="badge {{ $bill->is_active ? 'bg-label-success' : 'bg-label-secondary' }}">
              {{ $bill->is_active ? 'Active' : 'Inactive' }}
            </span>
          </dd>
          <dt class="col-5 fw-normal text-muted">Owner</dt>
          <dd class="col-7">
            @if($bill->user)
              <a href="{{ route('app-user-view', $bill->user->id) }}">{{ $bill->user->name }}</a>
            @else
              <span class="text-muted">Deleted user</span>
            @endif
          </dd>
        </dl>
        @if($bill->notes)
          <hr>
          <p class="mb-0 text-muted">{{ $bill->notes }}</p>
        @endif
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Schedule</h5></div>
      <div class="table-responsive">
        <table class="table table-sm">
          <thead class="table-light">
            <tr><th>Due Date</th><th>Amount</th><th>Status</th><th>Paid</th></tr>
          </thead>
          <tbody>
            @forelse($occurrences as $occurrence)
              <tr>
                <td>{{ $occurrence->due_date->format('d M Y') }}</td>
                <td>${{ number_format((float) $occurrence->amount, 2) }}</td>
                <td>
                  @php
                    $tone = match ($occurrence->status) {
                      'paid' => 'success',
                      'overdue' => 'danger',
                      'skipped' => 'secondary',
                      default => 'warning',
                    };
                  @endphp
                  <span class="badge bg-label-{{ $tone }}">{{ ucfirst($occurrence->status) }}</span>
                </td>
                <td class="text-muted">
                  {{ $occurrence->paid_at ? $occurrence->paid_at->format('d M Y') : '—' }}
                </td>
              </tr>
            @empty
              <tr><td colspan="4" class="text-center py-3 text-muted">No occurrences generated.</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
