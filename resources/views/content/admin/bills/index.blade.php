@extends('layouts/layoutMaster')

@section('title', 'Bills')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Management /</span> Bills
</h4>

<div class="row g-4 mb-4">
  @foreach([
    ['label' => 'Active Bills', 'value' => number_format($totals['bills']), 'icon' => 'tabler-receipt', 'tone' => 'primary'],
    ['label' => 'Due This Month', 'value' => '$' . number_format($totals['due_this_month'], 2), 'icon' => 'tabler-calendar-dollar', 'tone' => 'info'],
    ['label' => 'Paid This Month', 'value' => '$' . number_format($totals['paid_this_month'], 2), 'icon' => 'tabler-circle-check', 'tone' => 'success'],
    ['label' => 'Overdue', 'value' => number_format($totals['overdue']), 'icon' => 'tabler-alert-triangle', 'tone' => 'danger'],
  ] as $card)
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <p class="mb-1 text-muted">{{ $card['label'] }}</p>
            <h4 class="mb-0">{{ $card['value'] }}</h4>
          </div>
          <div class="avatar">
            <span class="avatar-initial rounded bg-label-{{ $card['tone'] }}">
              <i class="icon-base ti {{ $card['icon'] }}"></i>
            </span>
          </div>
        </div>
      </div>
    </div>
  @endforeach
</div>

<div class="card">
  <div class="card-header">
    <h5 class="mb-0">All Bills</h5>
    <small class="text-muted">Across every account. Customers manage their own bills in the app.</small>
  </div>

  <div class="card-body border-bottom">
    <form method="GET" action="{{ route('admin-bills') }}" class="row g-3">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search bill or user"
               value="{{ $filters['search'] ?? '' }}" maxlength="100" />
      </div>
      <div class="col-md-3">
        <select name="category_id" class="form-select">
          <option value="">Any category</option>
          @foreach($categories as $category)
            <option value="{{ $category->id }}" @selected((int) ($filters['category_id'] ?? 0) === $category->id)>
              {{ $category->name }}
            </option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <select name="recurrence" class="form-select">
          <option value="">Any recurrence</option>
          @foreach(['one_time' => 'One-time', 'weekly' => 'Weekly', 'biweekly' => 'Bi-weekly', 'monthly' => 'Monthly'] as $value => $label)
            <option value="{{ $value }}" @selected(($filters['recurrence'] ?? '') === $value)>{{ $label }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-label-primary flex-fill">Filter</button>
        <a href="{{ route('admin-bills') }}" class="btn btn-label-secondary">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>Bill</th>
          <th>User</th>
          <th>Category</th>
          <th>Amount</th>
          <th>Recurrence</th>
          <th>Next Due</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($bills as $bill)
          <tr>
            <td class="fw-medium">{{ $bill->name }}</td>
            <td>
              @if($bill->user)
                <a href="{{ route('app-user-view', $bill->user->id) }}">{{ $bill->user->name }}</a>
                <small class="d-block text-muted">{{ $bill->user->email }}</small>
              @else
                <span class="text-muted">Deleted user</span>
              @endif
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
            <td class="text-muted">{{ str_replace('_', '-', $bill->recurrence) }}</td>
            <td class="text-muted">{{ $bill->next_due_date?->format('d M Y') ?: '—' }}</td>
            <td class="text-end">
              <a href="{{ route('admin-bill-view', $bill->id) }}" class="btn btn-icon btn-text-secondary btn-sm">
                <i class="icon-base ti tabler-eye icon-md"></i>
              </a>
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center py-4 text-muted">No bills match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($bills->hasPages())
    <div class="card-footer d-flex justify-content-center">
      {{ $bills->links() }}
    </div>
  @endif
</div>
@endsection
