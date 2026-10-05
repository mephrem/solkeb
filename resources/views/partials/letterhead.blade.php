{{-- Hotel name, address and TIN at the top of a printed document. $right goes on the right-hand side. --}}
@php $S = \App\Models\Setting::class; @endphp
<div class="inv-head">
    <div>
        <img src="{{ asset('images/solkeb-logo.png') }}" alt="{{ $S::get('hotel_name', 'Solkeb Hotel') }}" style="height:44px;width:auto;display:block">
        <div class="inv-tagline">{{ $S::get('tagline', 'Peaceful and Comfortable!') }}</div>
        <div class="muted">{{ $S::get('city', 'Jijiga') }}, {{ $S::get('region', 'Somali Region, Ethiopia') }}</div>
        @if ($S::get('address'))<div class="muted">{{ $S::get('address') }}</div>@endif
        @if ($S::get('phone'))<div class="muted">Tel: {{ $S::get('phone') }}@if ($S::get('email')) · {{ $S::get('email') }}@endif</div>@endif
        @if ($S::get('tin'))<div class="muted">TIN: {{ $S::get('tin') }}</div>@endif
    </div>
    <div class="inv-meta">{{ $right }}</div>
</div>
