@extends('layouts.app')
@section('title', 'Hotel settings')
@section('content')
<div class="page-head"><div><h1>Hotel settings</h1><div class="muted">Shown on screens and printed bills</div></div></div>
<form method="POST" action="{{ route('settings.update') }}" style="max-width:860px">
    @csrf @method('PUT')
    <div class="card">
        <h2>Hotel details</h2>
        <div class="fields">
            <div class="field"><label>Hotel name *</label><input type="text" name="hotel_name" value="{{ old('hotel_name', $values['hotel_name']) }}" required></div>
            <div class="field"><label>City *</label><input type="text" name="city" value="{{ old('city', $values['city']) }}" required></div>
            <div class="field"><label>Region</label><input type="text" name="region" value="{{ old('region', $values['region']) }}"></div>
        </div>
        <div class="field"><label>Slogan</label><input type="text" name="tagline" value="{{ old('tagline', $values['tagline'] ?? 'Peaceful and Comfortable!') }}" placeholder="Peaceful and Comfortable!"><div class="small muted">Shown under the logo on the login page, the menu and printed bills.</div></div>
        <div class="field"><label>Address</label><input type="text" name="address" value="{{ old('address', $values['address']) }}" placeholder="e.g. Kebele 05, near ..."></div>
        <div class="fields">
            <div class="field"><label>Phone</label><input type="text" name="phone" value="{{ old('phone', $values['phone']) }}"></div>
            <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $values['email']) }}"></div>
            <div class="field"><label>TIN</label><input type="text" name="tin" value="{{ old('tin', $values['tin']) }}"></div>
        </div>
    </div>
    <div class="card">
        <h2>Bookings &amp; billing</h2>
        <div class="fields">
            <div class="field"><label>VAT % *</label><input type="number" step="0.01" min="0" max="100" name="vat_percent" value="{{ old('vat_percent', $values['vat_percent']) }}" required></div>
            <div class="field"><label>Check-in time *</label><input type="text" name="check_in_time" value="{{ old('check_in_time', $values['check_in_time']) }}" placeholder="14:00" required></div>
            <div class="field"><label>Check-out time *</label><input type="text" name="check_out_time" value="{{ old('check_out_time', $values['check_out_time']) }}" placeholder="12:00" required></div>
            <div class="field"><label>Booking code prefix *</label><input type="text" name="booking_prefix" value="{{ old('booking_prefix', $values['booking_prefix']) }}" maxlength="6" required></div>
        </div>
        <div class="fields">
            <div class="field"><label>Half-day extension until</label><input type="text" name="half_day_until" value="{{ old('half_day_until', $values['half_day_until'] ?: '14:30') }}" placeholder="14:30"></div>
            <div class="field"><label>Half-day price (% of the night)</label><input type="number" step="1" min="0" max="100" name="half_day_percent" value="{{ old('half_day_percent', $values['half_day_percent'] ?: 50) }}"></div>
        </div>
        <p class="small muted" style="margin-top:0">A guest leaving after the check-out time can stay until the half-day time for this share of one night's price.</p>
        <p class="small muted" style="margin-top:0">Prices are treated as VAT-inclusive; the bill shows how much of the total is VAT.</p>
        <div class="field"><label>Message at the bottom of printed bills</label><input type="text" name="invoice_footer" value="{{ old('invoice_footer', $values['invoice_footer']) }}" placeholder="Thank you for staying with us."></div>
        <div class="field"><label class="check"><input type="checkbox" name="take_deposits" value="1" @checked(old('take_deposits', $values['take_deposits']))> Take a deposit (advance payment) when booking</label>
            <div class="small muted">Leave unticked if guests pay nothing in advance: they pay during the stay or at check-out.</div></div>
    </div>
    <button class="btn" type="submit">Save settings</button>
</form>
@endsection
