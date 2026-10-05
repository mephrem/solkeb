@extends('layouts.app')
@section('title', 'New booking')
@section('content')
<div class="page-head"><h1>New booking</h1></div>

{{-- Step 1: dates (reloads the page with the rooms free for those dates) --}}
<form class="card filters" method="GET" action="{{ route('reservations.create') }}">
    @if ($guest)<input type="hidden" name="guest_id" value="{{ $guest->id }}">@endif
    <div><label for="ci">Check-in</label><input type="date" id="ci" name="check_in" value="{{ $checkIn }}" min="{{ today()->toDateString() }}"></div>
    <div><label for="co">Check-out</label><input type="date" id="co" name="check_out" value="{{ $checkOut }}" min="{{ today()->addDay()->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show free rooms</button></div>
    <div class="muted" style="align-self:center">{{ $nights }} night{{ $nights == 1 ? '' : 's' }}</div>
</form>

{{-- Step 2: rooms, guest, payment --}}
<form method="POST" action="{{ route('reservations.store') }}">
    @csrf
    <input type="hidden" name="check_in_date" value="{{ $checkIn }}">
    <input type="hidden" name="check_out_date" value="{{ $checkOut }}">

    <div class="grid grid-main">
        <div>
            <div class="card">
                <div class="card-head"><h2>Choose rooms</h2><span class="muted small">{{ $roomsByType->flatten()->count() }} free</span></div>
                @forelse ($roomsByType as $type => $rooms)
                    <div class="pick-group">
                        <h3>{{ $type }} · {{ number_format($rooms->first()->rate()) }} ETB / night</h3>
                        <div class="picks">
                            @foreach ($rooms as $room)
                                <label class="pick">
                                    <input type="checkbox" name="rooms[]" value="{{ $room->id }}" @checked(in_array($room->id, old('rooms', [])))>
                                    <span><strong>{{ $room->number }}</strong><span class="pick-sub">
                                        {{ number_format($room->rate()) }} ETB
                                        @if ($room->housekeeping_status === 'dirty') · needs cleaning @endif
                                    </span></span>
                                </label>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="empty">No rooms are free for these dates.</p>
                @endforelse
            </div>

        </div>

        <div>
            <div class="card">
                <h2>Guest</h2>
                @if ($guest)
                    <input type="hidden" name="guest_id" value="{{ $guest->id }}">
                    <p><strong>{{ $guest->full_name }}</strong> · {{ $guest->phone }}
                        @if ($guest->is_blacklisted) <span class="badge b-cancelled">Blacklisted</span> @endif
                        · <a href="{{ route('reservations.create', ['check_in' => $checkIn, 'check_out' => $checkOut]) }}">different guest</a></p>
                @else
                    <p class="small muted">If the phone number belongs to a returning guest, the booking is added to their existing record.</p>
                    <div class="fields">
                        <div class="field"><label>First name *</label><input type="text" name="guest[first_name]" value="{{ old('guest.first_name') }}" required></div>
                        <div class="field"><label>Father's name</label><input type="text" name="guest[father_name]" value="{{ old('guest.father_name') }}"></div>
                    </div>
                    <div class="fields">
                        <div class="field"><label>Phone *</label><input type="tel" name="guest[phone]" value="{{ old('guest.phone') }}" placeholder="09..." required></div>
                        <div class="field"><label>Gender</label>
                            <select name="guest[gender]"><option value="">—</option>
                                <option value="male" @selected(old('guest.gender') === 'male')>Male</option>
                                <option value="female" @selected(old('guest.gender') === 'female')>Female</option>
                            </select></div>
                        @include('partials.nationality', ['name' => 'guest[nationality]', 'value' => old('guest.nationality')])
                    </div>
                    <div class="fields">
                        <div class="field"><label>ID type</label>
                            <select name="guest[id_type]"><option value="">— add at check-in —</option>
                                @foreach (['kebele_id' => 'Kebele ID', 'national_id' => 'National ID (Fayda)', 'passport' => 'Passport', 'driving_license' => 'Driving licence', 'other' => 'Other'] as $v => $l)
                                    <option value="{{ $v }}" @selected(old('guest.id_type') === $v)>{{ $l }}</option>
                                @endforeach
                            </select></div>
                        <div class="field"><label>ID number</label><input type="text" name="guest[id_number]" value="{{ old('guest.id_number') }}"></div>
                    </div>
                @endif
            </div>

            <div class="card">
                <h2>Stay details</h2>
                <div class="fields">
                    <div class="field"><label>Adults</label><input type="number" name="adults" min="1" value="{{ old('adults', 1) }}"></div>
                    <div class="field"><label>Children</label><input type="number" name="children" min="0" value="{{ old('children', 0) }}"></div>
                </div>
                <div class="field"><label>Booked via</label>
                    <select name="source">
                        @foreach (['walk_in' => 'Walk-in', 'phone' => 'Phone', 'website' => 'Website', 'booking_com' => 'Booking.com', 'agent' => 'Travel agent', 'corporate' => 'Corporate / NGO'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('source', 'walk_in') === $v)>{{ $l }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label>Special requests</label><textarea name="special_requests">{{ old('special_requests') }}</textarea></div>
            </div>

            @include('partials.bill-to', ['orgId' => null, 'credit' => false])

            @if (\App\Models\Setting::get('take_deposits'))
            <div class="card">
                <h2>Deposit <span class="muted small">(optional)</span></h2>
                <div class="field"><label>Amount (ETB)</label><input type="number" step="0.01" min="0" name="deposit" value="{{ old('deposit') }}"></div>
                @include('partials.received-into', ['name' => 'deposit_into', 'method' => 'deposit_method'])
                <div class="field"><label>Transaction / receipt no.</label><input type="text" name="deposit_reference" value="{{ old('deposit_reference') }}">
                    @error('deposit_reference')<div class="error-text">{{ $message }}</div>@enderror</div>
                @include('partials.paid-by', ['name' => 'deposit_payer', 'org' => null, 'default' => ''])
            </div>
            @endif
        </div>
    </div>

    <div class="submit-bar">
        <a class="btn btn-light" href="{{ route('reservations.index') }}">Cancel</a>
        <button class="btn" type="submit" @disabled($roomsByType->isEmpty())>Create booking</button>
    </div>
</form>
@endsection
