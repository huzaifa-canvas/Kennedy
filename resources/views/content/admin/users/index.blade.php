@extends('layouts/layoutMaster')

@section('title', 'Users')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Management /</span> Users
</h4>

<div class="row g-4 mb-4">
  @foreach([
    ['label' => 'Total Users', 'value' => $counts['total'], 'icon' => 'tabler-users', 'tone' => 'primary'],
    ['label' => 'Active', 'value' => $counts['active'], 'icon' => 'tabler-user-check', 'tone' => 'success'],
    ['label' => 'Suspended', 'value' => $counts['suspended'], 'icon' => 'tabler-user-off', 'tone' => 'danger'],
    ['label' => 'Admins', 'value' => $counts['admins'], 'icon' => 'tabler-shield-lock', 'tone' => 'info'],
  ] as $card)
    <div class="col-sm-6 col-lg-3">
      <div class="card h-100">
        <div class="card-body d-flex justify-content-between align-items-center">
          <div>
            <p class="mb-1 text-muted">{{ $card['label'] }}</p>
            <h4 class="mb-0">{{ number_format($card['value']) }}</h4>
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
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-4">
    <h5 class="mb-0">All Users</h5>
    <a href="{{ route('app-user-create') }}" class="btn btn-primary">
      <i class="icon-base ti tabler-plus me-2"></i> Add New User
    </a>
  </div>

  {{-- Filters --}}
  <div class="card-body border-bottom">
    <form method="GET" action="{{ route('app-user-list') }}" class="row g-3">
      <div class="col-md-4">
        <input type="text" name="search" class="form-control" placeholder="Search name, email or phone"
               value="{{ $filters['search'] ?? '' }}" maxlength="100" />
      </div>
      <div class="col-md-2">
        <select name="status" class="form-select">
          <option value="">Any status</option>
          <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
          <option value="suspended" @selected(($filters['status'] ?? '') === 'suspended')>Suspended</option>
        </select>
      </div>
      <div class="col-md-2">
        <select name="role" class="form-select">
          <option value="">Any role</option>
          <option value="user" @selected(($filters['role'] ?? '') === 'user')>Customer</option>
          <option value="admin" @selected(($filters['role'] ?? '') === 'admin')>Admin</option>
        </select>
      </div>
      <div class="col-md-2">
        <select name="plan" class="form-select">
          <option value="">Any plan</option>
          @foreach($plans as $plan)
            <option value="{{ $plan->slug }}" @selected(($filters['plan'] ?? '') === $plan->slug)>{{ $plan->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2 d-flex gap-2">
        <button type="submit" class="btn btn-label-primary flex-fill">Filter</button>
        <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">Reset</a>
      </div>
    </form>
  </div>

  <div class="table-responsive">
    <table class="table table-hover">
      <thead class="table-light">
        <tr>
          <th>User</th>
          <th>Plan</th>
          <th>Pay Frequency</th>
          <th>Status</th>
          <th>Joined</th>
          <th class="text-end">Actions</th>
        </tr>
      </thead>
      <tbody>
        @forelse($users as $user)
          <tr>
            <td>
              <div class="d-flex align-items-center">
                <div class="avatar avatar-sm me-3">
                  <img src="{{ $user->avatar_url }}" alt="Avatar" class="rounded-circle" />
                </div>
                <div>
                  <a href="{{ route('app-user-view', $user->id) }}" class="fw-medium d-block">{{ $user->name }}</a>
                  <small class="text-muted">{{ $user->email }}</small>
                  @if($user->isAdmin())
                    <span class="badge bg-label-info ms-1">Admin</span>
                  @endif
                </div>
              </div>
            </td>
            <td>
              @php $plan = $user->plan(); @endphp
              <span class="badge {{ $plan->isFree() ? 'bg-label-secondary' : 'bg-label-warning' }}">{{ $plan->name }}</span>
            </td>
            <td class="text-muted">
              {{ \App\Models\IncomeSource::frequencyLabel($user->pay_frequency) ?? '—' }}
            </td>
            <td>
              <span class="badge {{ $user->isActive() ? 'bg-label-success' : 'bg-label-danger' }}">
                {{ ucfirst($user->status) }}
              </span>
            </td>
            <td class="text-muted">{{ $user->created_at->format('d M Y') }}</td>
            <td>
              <div class="d-flex gap-1 justify-content-end">
                <a href="{{ route('app-user-view', $user->id) }}" class="btn btn-icon btn-text-secondary btn-sm" title="View">
                  <i class="icon-base ti tabler-eye icon-md"></i>
                </a>
                <a href="{{ route('app-user-edit', $user->id) }}" class="btn btn-icon btn-text-primary btn-sm" title="Edit">
                  <i class="icon-base ti tabler-edit icon-md"></i>
                </a>
                @if($user->id !== auth()->id())
                  <form action="{{ route('app-user-status', $user->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('PATCH')
                    <button type="submit" class="btn btn-icon btn-text-warning btn-sm"
                            title="{{ $user->isActive() ? 'Suspend' : 'Activate' }}">
                      <i class="icon-base ti {{ $user->isActive() ? 'tabler-lock' : 'tabler-lock-open' }} icon-md"></i>
                    </button>
                  </form>
                  <form action="{{ route('app-user-delete', $user->id) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-icon btn-text-danger btn-sm" title="Delete"
                            onclick="return confirm('Delete {{ $user->name }} and all of their data? This cannot be undone.')">
                      <i class="icon-base ti tabler-trash icon-md"></i>
                    </button>
                  </form>
                @endif
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="6" class="text-center py-4 text-muted">No users match these filters.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if($users->hasPages())
    <div class="card-footer d-flex justify-content-center">
      {{ $users->links() }}
    </div>
  @endif
</div>
@endsection
