@extends('layouts/layoutMaster')

@section('title', 'Edit ' . $plan->name)

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light"><a href="{{ route('admin-plans') }}">Plans</a> /</span> {{ $plan->name }}
</h4>

<form method="POST" action="{{ route('admin-plan-update', $plan->id) }}">
  @csrf
  @method('PUT')

  <div class="row g-4">
    <div class="col-xl-7">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Plan Details</h5></div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-6">
              <label class="form-label" for="name">Name</label>
              <input type="text" id="name" name="name" class="form-control"
                     value="{{ old('name', $plan->name) }}" required maxlength="80" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="tagline">Tagline</label>
              <input type="text" id="tagline" name="tagline" class="form-control"
                     value="{{ old('tagline', $plan->tagline) }}" maxlength="160" />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="price">Price</label>
              <input type="number" step="0.01" min="0" id="price" name="price" class="form-control"
                     value="{{ old('price', $plan->price) }}" required />
            </div>
            <div class="col-md-4">
              <label class="form-label" for="billing_period">Billing Period</label>
              <select id="billing_period" name="billing_period" class="form-select" required>
                @foreach(['monthly', 'yearly', 'lifetime'] as $period)
                  <option value="{{ $period }}" @selected(old('billing_period', $plan->billing_period) === $period)>
                    {{ ucfirst($period) }}
                  </option>
                @endforeach
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label" for="sort_order">Sort Order</label>
              <input type="number" min="0" max="999" id="sort_order" name="sort_order" class="form-control"
                     value="{{ old('sort_order', $plan->sort_order) }}" />
            </div>
            <div class="col-md-6">
              <label class="form-label" for="store_product_id">App Store Product ID</label>
              <input type="text" id="store_product_id" name="store_product_id" class="form-control"
                     value="{{ old('store_product_id', $plan->store_product_id) }}" maxlength="191" />
              <small class="text-muted">Must match the App Store Connect product attached in RevenueCat.</small>
            </div>
            <div class="col-md-6">
              <label class="form-label" for="play_store_product_id">Google Play Product ID</label>
              <input type="text" id="play_store_product_id" name="play_store_product_id" class="form-control"
                     value="{{ old('play_store_product_id', $plan->play_store_product_id) }}" maxlength="191" />
              <small class="text-muted">As RevenueCat reports it, e.g. <code>saverr_pro:monthly</code> (subscription:base plan).</small>
            </div>
          </div>
        </div>
      </div>

      <div class="card mb-4">
        <div class="card-header">
          <h5 class="mb-0">Limits</h5>
          <small class="text-muted">Leave blank for unlimited.</small>
        </div>
        <div class="card-body">
          <div class="row g-4">
            <div class="col-md-3">
              <label class="form-label" for="bill_limit">Bills</label>
              <input type="number" min="0" id="bill_limit" name="bill_limit" class="form-control"
                     value="{{ old('bill_limit', $plan->bill_limit) }}" placeholder="∞" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="income_source_limit">Income Sources</label>
              <input type="number" min="0" id="income_source_limit" name="income_source_limit" class="form-control"
                     value="{{ old('income_source_limit', $plan->income_source_limit) }}" placeholder="∞" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="savings_jar_limit">Savings Jars</label>
              <input type="number" min="0" id="savings_jar_limit" name="savings_jar_limit" class="form-control"
                     value="{{ old('savings_jar_limit', $plan->savings_jar_limit) }}" placeholder="∞" />
            </div>
            <div class="col-md-3">
              <label class="form-label" for="history_days">History (days)</label>
              <input type="number" min="1" id="history_days" name="history_days" class="form-control"
                     value="{{ old('history_days', $plan->history_days) }}" placeholder="∞" />
            </div>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h5 class="mb-0">Paywall Feature List</h5>
          <small class="text-muted">Shown on the Go Premium screen.</small>
        </div>
        <div class="card-body">
          @php $features = old('features', $plan->features ?? []); @endphp
          <div id="feature-rows">
            @foreach($features as $index => $feature)
              <div class="d-flex align-items-center gap-3 mb-3">
                <input type="text" name="features[{{ $index }}][label]" class="form-control"
                       value="{{ $feature['label'] ?? '' }}" maxlength="120" />
                <div class="form-check form-switch mb-0 text-nowrap">
                  <input class="form-check-input" type="checkbox" value="1"
                         name="features[{{ $index }}][included]" @checked($feature['included'] ?? false) />
                  <label class="form-check-label">Included</label>
                </div>
              </div>
            @endforeach
          </div>
          @for($i = count($features); $i < count($features) + 2; $i++)
            <div class="d-flex align-items-center gap-3 mb-3">
              <input type="text" name="features[{{ $i }}][label]" class="form-control"
                     placeholder="New feature line" maxlength="120" />
              <div class="form-check form-switch mb-0 text-nowrap">
                <input class="form-check-input" type="checkbox" value="1" name="features[{{ $i }}][included]" />
                <label class="form-check-label">Included</label>
              </div>
            </div>
          @endfor
          <small class="text-muted">Clear a line to remove it.</small>
        </div>
      </div>
    </div>

    <div class="col-xl-5">
      <div class="card mb-4">
        <div class="card-header"><h5 class="mb-0">Capabilities</h5></div>
        <div class="card-body">
          @foreach([
            'allows_smart_splits' => ['Smart Splits', 'Spread large bills across the paychecks before the due date.'],
            'allows_advanced_recurrence' => ['Weekly & Bi-weekly Bills', 'Unlocks recurrence beyond one-time and monthly.'],
            'allows_custom_reminders' => ['Custom Reminders', 'Per-bill reminder timing.'],
            'allows_calendar_themes' => ['Calendar Themes', 'Colour and layout customisation.'],
          ] as $field => [$label, $help])
            <div class="form-check form-switch mb-4">
              <input class="form-check-input" type="checkbox" value="1" id="{{ $field }}" name="{{ $field }}"
                     @checked(old($field, $plan->$field)) />
              <label class="form-check-label fw-medium" for="{{ $field }}">{{ $label }}</label>
              <small class="d-block text-muted">{{ $help }}</small>
            </div>
          @endforeach

          <hr>

          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active"
                   @checked(old('is_active', $plan->is_active)) />
            <label class="form-check-label fw-medium" for="is_active">Visible in the app</label>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-body d-flex gap-3">
          <button type="submit" class="btn btn-primary flex-fill">Save Plan</button>
          <a href="{{ route('admin-plans') }}" class="btn btn-label-secondary">Cancel</a>
        </div>
      </div>
    </div>
  </div>
</form>
@endsection
