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
        <form method="POST" action="{{ route('admin-category-store') }}" enctype="multipart/form-data">
          @csrf
          <div class="mb-3">
            <label class="form-label" for="new-name">Name</label>
            <input type="text" id="new-name" name="name" class="form-control" required maxlength="60"
                   value="{{ old('name') }}" placeholder="e.g. Childcare" />
          </div>
          <div class="mb-3">
            <label class="form-label" for="new-icon-image">Icon</label>
            <div class="d-flex align-items-center gap-3">
              <div class="cat-icon-thumb" id="new-icon-preview">
                <i class="icon-base ti tabler-photo text-muted"></i>
              </div>
              <input type="file" id="new-icon-image" name="icon_image" class="form-control"
                     accept="image/png,image/jpeg,image/webp" data-preview="new-icon-preview" />
            </div>
            <small class="text-muted">PNG, JPG or WebP, square, up to 512 KB (max 1024×1024). If left empty, a default receipt icon is used.</small>
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
              <th>Icon</th>
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
                <td style="min-width: 120px;">
                  <div class="d-flex align-items-center gap-2">
                    <div class="cat-icon-thumb" id="cat-icon-preview-{{ $category->id }}">
                      @if($category->icon_url)
                        <img src="{{ $category->icon_url }}" alt="{{ $category->name }} icon" />
                      @else
                        <i class="icon-base ti {{ $category->icon }}"></i>
                      @endif
                    </div>
                    <div class="d-flex flex-column gap-1">
                      <label class="btn btn-xs btn-label-primary mb-0" title="Upload icon image">
                        <i class="icon-base ti tabler-upload icon-xs me-1"></i>{{ $category->icon_image ? 'Replace' : 'Upload' }}
                        <input type="file" name="icon_image" form="cat-form-{{ $category->id }}" class="d-none"
                               accept="image/png,image/jpeg,image/webp"
                               data-preview="cat-icon-preview-{{ $category->id }}" />
                      </label>
                      @if($category->icon_image)
                        <div class="form-check mb-0">
                          <input class="form-check-input" type="checkbox" value="1" name="remove_icon_image"
                                 id="cat-remove-icon-{{ $category->id }}" form="cat-form-{{ $category->id }}" />
                          <label class="form-check-label small" for="cat-remove-icon-{{ $category->id }}">Remove</label>
                        </div>
                      @endif
                    </div>
                  </div>
                </td>
                <td>
                  <form method="POST" action="{{ route('admin-category-update', $category->id) }}"
                        id="cat-form-{{ $category->id }}" class="d-flex align-items-center gap-2"
                        enctype="multipart/form-data">
                    @csrf
                    @method('PUT')
                    <input type="text" name="name" value="{{ $category->name }}"
                           class="form-control form-control-sm" style="max-width: 200px;" maxlength="60" required />
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

@section('page-style')
<style>
  .cat-icon-thumb {
    width: 40px; height: 40px; flex: 0 0 40px;
    display: flex; align-items: center; justify-content: center;
    border-radius: .5rem; background: var(--bs-gray-100); overflow: hidden; font-size: 1.25rem;
  }
  .cat-icon-thumb img { width: 100%; height: 100%; object-fit: contain; }
</style>
@endsection

@section('page-script')
<script>
  // Show the chosen image straight away; it is saved with the row's Save button.
  document.querySelectorAll('input[type="file"][data-preview]').forEach(function (input) {
    input.addEventListener('change', function () {
      var box = document.getElementById(input.dataset.preview);
      var file = input.files && input.files[0];
      if (!box || !file) return;
      var img = document.createElement('img');
      img.alt = 'Icon preview';
      img.src = URL.createObjectURL(file);
      box.replaceChildren(img);
    });
  });
</script>
@endsection
