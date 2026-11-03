@extends('layouts/layoutMaster')

@section('title', 'Bill Categories')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">Management /</span> Bill Categories
</h4>

<div class="row g-4">
  {{-- Add new --}}
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><h5 class="mb-0">Add Category</h5></div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin-category-store') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="new-name">Name</label>
            <input type="text" id="new-name" name="name" class="form-control" required maxlength="60"
                   value="{{ old('name') }}" placeholder="e.g. Childcare" />
          </div>
          <div class="mb-3">
            <label class="form-label" for="new-icon">Icon</label>
            <input type="text" id="new-icon" name="icon" class="form-control" required maxlength="64"
                   value="{{ old('icon', 'tabler-receipt') }}" pattern="[a-z0-9\-]+" />
            <small class="text-muted">Tabler icon name, lowercase and hyphens only.</small>
          </div>
          <div class="mb-3">
            <label class="form-label" for="new-color">Colour</label>
            <input type="color" id="new-color" name="color" class="form-control form-control-color w-100"
                   value="{{ old('color', '#F97316') }}" required />
          </div>
          <div class="mb-3">
            <label class="form-label" for="new-sort">Sort Order</label>
            <input type="number" id="new-sort" name="sort_order" class="form-control" min="0" max="999"
                   value="{{ old('sort_order', 0) }}" />
          </div>
          <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" value="1" id="new-quick" name="is_quick_pick" />
            <label class="form-check-label" for="new-quick">Show as an onboarding quick pick</label>
          </div>
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" value="1" id="new-active" name="is_active" checked />
            <label class="form-check-label" for="new-active">Active</label>
          </div>
          <button type="submit" class="btn btn-primary w-100">Add Category</button>
        </form>
      </div>
    </div>
  </div>

  {{-- Existing --}}
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header">
        <h5 class="mb-0">Categories ({{ $categories->count() }})</h5>
        <small class="text-muted">These drive the Add Bill screen in the app.</small>
      </div>
      <div class="table-responsive">
        <table class="table table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Category</th>
              <th>Bills</th>
              <th>Quick Pick</th>
              <th>Status</th>
              <th class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            @foreach($categories as $category)
              <tr>
                <td>
                  <form method="POST" action="{{ route('admin-category-update', $category->id) }}"
                        id="cat-form-{{ $category->id }}" class="d-flex align-items-center gap-2">
                    @csrf
                    @method('PUT')
                    <input type="color" name="color" value="{{ $category->color }}"
                           class="form-control form-control-color" style="width: 42px;" />
                    <input type="text" name="name" value="{{ $category->name }}"
                           class="form-control form-control-sm" style="max-width: 160px;" maxlength="60" required />
                    <input type="text" name="icon" value="{{ $category->icon }}"
                           class="form-control form-control-sm" style="max-width: 150px;" maxlength="64" required />
                    <input type="number" name="sort_order" value="{{ $category->sort_order }}"
                           class="form-control form-control-sm" style="width: 72px;" min="0" max="999" />
                  </form>
                </td>
                <td><span class="badge bg-label-secondary">{{ $category->bills_count }}</span></td>
                <td>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" value="1" name="is_quick_pick"
                           form="cat-form-{{ $category->id }}" @checked($category->is_quick_pick) />
                  </div>
                </td>
                <td>
                  <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" value="1" name="is_active"
                           form="cat-form-{{ $category->id }}" @checked($category->is_active) />
                  </div>
                </td>
                <td class="text-end">
                  <div class="d-flex gap-1 justify-content-end">
                    <button type="submit" form="cat-form-{{ $category->id }}"
                            class="btn btn-icon btn-text-primary btn-sm" title="Save">
                      <i class="icon-base ti tabler-device-floppy icon-md"></i>
                    </button>
                    <form method="POST" action="{{ route('admin-category-delete', $category->id) }}">
                      @csrf
                      @method('DELETE')
                      <button type="submit" class="btn btn-icon btn-text-danger btn-sm" title="Delete"
                              onclick="return confirm('Delete {{ $category->name }}?')">
                        <i class="icon-base ti tabler-trash icon-md"></i>
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection
