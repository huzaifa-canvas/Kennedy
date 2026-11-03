@extends('layouts/layoutMaster')

@section('title', 'Edit ' . $user->name)

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"><a href="{{ route('app-user-list') }}">Users</a> /</span> Edit {{ $user->name }}
</h4>

<div class="row">
  <div class="col-xl-8 col-lg-10 mx-auto">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">User Details</h5>
        <a href="{{ route('app-user-view', $user->id) }}" class="btn btn-sm btn-label-secondary">View Profile</a>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('app-user-update', $user->id) }}">
          @csrf
          @method('PUT')
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                     value="{{ old('name', $user->name) }}" required maxlength="100" />
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                     value="{{ old('email', $user->email) }}" required maxlength="255" />
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Phone</label>
              <input type="text" id="phone" name="phone" class="form-control"
                     value="{{ old('phone', $user->phone) }}" maxlength="32" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="role">Role</label>
              <select id="role" name="role" class="form-select" required>
                <option value="user" @selected(old('role', $user->role) === 'user')>Customer</option>
                <option value="admin" @selected(old('role', $user->role) === 'admin')>Admin</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label" for="status">Status</label>
              <select id="status" name="status" class="form-select" required>
                <option value="active" @selected(old('status', $user->status) === 'active')>Active</option>
                <option value="suspended" @selected(old('status', $user->status) === 'suspended')>Suspended</option>
              </select>
            </div>

            <div class="col-12"><hr class="my-0"></div>

            <div class="col-md-6">
              <label class="form-label" for="password">New Password <span class="text-muted">(leave blank to keep)</span></label>
              <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                     placeholder="············" minlength="8" />
              @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <small class="text-muted">Changing this signs the user out of every device.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password_confirmation">Confirm New Password</label>
              <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                     placeholder="············" minlength="8" />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Save Changes</button>
            <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
