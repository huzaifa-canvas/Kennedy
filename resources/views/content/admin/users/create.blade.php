@extends('layouts/layoutMaster')

@section('title', 'Add New User')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"><a href="{{ route('app-user-list') }}">Users</a> /</span> Add New
</h4>

<div class="row">
  <div class="col-xl-8 col-lg-10 mx-auto">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">New User</h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('app-user-store') }}">
          @csrf
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Full Name</label>
              <input type="text" id="name" name="name" class="form-control @error('name') is-invalid @enderror"
                     value="{{ old('name') }}" required maxlength="100" />
              @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="email">Email</label>
              <input type="email" id="email" name="email" class="form-control @error('email') is-invalid @enderror"
                     value="{{ old('email') }}" required maxlength="255" />
              @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-6">
              <label class="form-label" for="phone">Phone <span class="text-muted">(optional)</span></label>
              <input type="text" id="phone" name="phone" class="form-control" value="{{ old('phone') }}" maxlength="32" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="role">Role</label>
              <select id="role" name="role" class="form-select" required>
                <option value="user" @selected(old('role', 'user') === 'user')>Customer</option>
                <option value="admin" @selected(old('role') === 'admin')>Admin</option>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password">Password</label>
              <input type="password" id="password" name="password" class="form-control @error('password') is-invalid @enderror"
                     placeholder="············" required minlength="8" />
              @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
              <small class="text-muted">At least 8 characters, with letters and numbers.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="password_confirmation">Confirm Password</label>
              <input type="password" id="password_confirmation" name="password_confirmation" class="form-control"
                     placeholder="············" required minlength="8" />
            </div>
          </div>
          <div class="mt-6">
            <button type="submit" class="btn btn-primary me-3">Create User</button>
            <a href="{{ route('app-user-list') }}" class="btn btn-label-secondary">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
