@php
  // Saverr brand mark: the wallet-and-coin from the app's Set Aside card.
  $width = $width ?? '32';
  $height = $height ?? '22';
@endphp

<span class="app-brand-logo-mark d-inline-flex">
  <svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 32 22" fill="none"
    xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ config('variables.templateName') }}">
    <title>{{ config('variables.templateName') }}</title>

    {{-- wallet flap --}}
    <rect x="2.5" y="0.75" width="19" height="7.5" rx="3" fill="#FDBA74" />

    {{-- wallet body --}}
    <rect x="0.5" y="4.5" width="24" height="16.75" rx="4.5" fill="#F97316" />

    {{-- coin tucked into the pocket --}}
    <circle cx="25" cy="13.5" r="6.5" fill="#FFF3E6" />
    <circle cx="25" cy="13.5" r="2.6" fill="#F97316" />
  </svg>
</span>
