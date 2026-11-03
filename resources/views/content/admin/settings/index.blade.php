@extends('layouts/layoutMaster')

@section('title', 'Settings')

@section('content')

@include('_partials.admin-alerts')

<h4 class="py-3 mb-4">
  <span class="text-muted fw-light">System /</span> Settings
</h4>

<form method="POST" action="{{ route('admin-settings-update') }}">
  @csrf
  @method('PUT')

  <div class="row g-4">
    @foreach($groups as $group => $settings)
      <div class="col-xl-6">
        <div class="card h-100">
          <div class="card-header">
            <h5 class="mb-0">{{ ucfirst($group) }}</h5>
          </div>
          <div class="card-body">
            @foreach($settings as $setting)
              <div class="mb-4">
                <label class="form-label" for="setting-{{ $setting->key }}">{{ $setting->label }}</label>

                @if($setting->type === 'boolean')
                  <div class="form-check form-switch">
                    <input type="hidden" name="settings[{{ $setting->key }}]" value="0" />
                    <input class="form-check-input" type="checkbox" id="setting-{{ $setting->key }}"
                           name="settings[{{ $setting->key }}]" value="1"
                           @checked(filter_var($setting->value, FILTER_VALIDATE_BOOLEAN)) />
                    <label class="form-check-label" for="setting-{{ $setting->key }}">Enabled</label>
                  </div>
                @elseif($setting->type === 'text')
                  <textarea id="setting-{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                            rows="3" class="form-control" maxlength="2000">{{ $setting->value }}</textarea>
                @elseif(in_array($setting->type, ['integer', 'decimal'], true))
                  <input type="number" @if($setting->type === 'decimal') step="0.01" @endif
                         id="setting-{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                         class="form-control" value="{{ $setting->value }}" />
                @else
                  <input type="text" id="setting-{{ $setting->key }}" name="settings[{{ $setting->key }}]"
                         class="form-control" value="{{ $setting->value }}" maxlength="2000" />
                @endif

                <small class="text-muted"><code>{{ $setting->key }}</code></small>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    @endforeach
  </div>

  <div class="card mt-4">
    <div class="card-body d-flex gap-3">
      <button type="submit" class="btn btn-primary">Save Settings</button>
      <a href="{{ route('pages-home') }}" class="btn btn-label-secondary">Cancel</a>
    </div>
  </div>
</form>
@endsection
