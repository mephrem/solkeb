@extends('layouts.app')
@section('title', 'Edit '.$r->code)
@section('content')
@php $inHouse = $r->status === 'checked_in'; @endphp
<div class="page-head">
    <div>
        <h1>{{ $inHouse ? 'Extend or change stay' : 'Edit booking' }} · {{ $r->code }}</h1>
        <div class="muted">{{ $r->guest->full_name }} · {{ $r->guest->phone }}</div>
    </div>
    <a class="btn btn-light" href="{{ route('reservations.show', $r) }}">Back to booking</a>
</div>

@unless ($inHouse)
{{-- Change dates: reloads the page with the rooms free for those dates --}}
<form class="card filters" method="GET" action="{{ route('reservations.edit', $r) }}">
    <div><label for="ci">Check-in</label><input type="date" id="ci" name="check_in" value="{{ $checkIn }}" min="{{ today()->toDateString() }}"></div>
    <div><label for="co">Check-out</label><input type="date" id="co" name="check_out" value="{{ $checkOut }}" min="{{ today()->addDay()->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show free rooms</button></div>
    <div class="muted" style="align-self:center">{{ $nights }} night{{ $nights == 1 ? '' : 's' }}</div>
</form>
@endunless

<form method="POST" action="{{ route('reservations.update', $r) }}"
      data-confirm-title="Save changes to {{ $r->code }}?"
      data-confirm="{{ $inHouse ? 'Room charges will be recalculated for the new check-out date.' : 'The booking will be updated with the dates and rooms you picked.' }}"
      data-confirm-button="Save changes">
    @csrf @method('PUT')
    <div class="grid grid-main">
        <div>
            @if ($inHouse)
                <div class="card">
                    <h2>Stay dates</h2>
                    <div class="fields">
                        <div class="field"><label>Checked in</label><input type="text" value="{{ $r->check_in_date->format('D d M Y') }}" disabled></div>
                        <div class="field"><label for="co">New check-out date *</label>
                            <input type="date" id="co" name="check_out_date" value="{{ old('check_out_date', $r->check_out_date->toDateString()) }}" min="{{ today()->addDay()->toDateString() }}" required></div>
                    </div>
                    <p class="small muted" style="margin:0">Room {{ $r->rooms->pluck('number')->join(', ') }} · currently {{ $r->nights() }} night(s). The bill is updated to the new number of nights. The room must be free for the extra nights.</p>
                </div>
            @else
                <input type="hidden" name="check_in_date" value="{{ $checkIn }}">
                <input type="hidden" name="check_out_date" value="{{ $checkOut }}">
                <div class="card">
                    <div class="card-head"><h2>Rooms</h2><span class="muted small">{{ $roomsByType->flatten()->count() }} free for these dates</span></div>
                    @if ($unavailable->isNotEmpty())
                        <div class="alert alert-error">Room {{ $r->rooms->whereIn('id', $unavailable)->pluck('number')->join(', ') }} is not free for these dates. Choose another room.</div>
                    @endif
                    @forelse ($roomsByType as $type => $rooms)
                        <div class="pick-group">
                            <h3>{{ $type }}</h3>
                            <div class="picks">
                                @foreach ($rooms as $room)
                                    @php $booked = $r->rooms->firstWhere('id', $room->id); @endphp
                                    <label class="pick">
                                        <input type="checkbox" name="rooms[]" value="{{ $room->id }}" @checked(in_array($room->id, old('rooms', $current)))>
                                        <span><strong>{{ $room->number }}</strong><span class="pick-sub">
                                            {{ number_format($booked ? $booked->pivot->nightly_rate : $room->rate()) }} ETB{{ $booked ? ' · booked rate' : '' }}
                                        </span></span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <p class="empty">No rooms are free for these dates.</p>
                    @endforelse
                </div>
            @endif
        </div>
        <div>
            <div class="card">
                <h2>Stay details</h2>
                <div class="fields">
                    <div class="field"><label>Adults</label><input type="number" name="adults" min="1" value="{{ old('adults', $r->adults) }}"></div>
                    <div class="field"><label>Children</label><input type="number" name="children" min="0" value="{{ old('children', $r->children) }}"></div>
                </div>
                <div class="field"><label>Booked via</label>
                    <select name="source">
                        @foreach (['walk_in' => 'Walk-in', 'phone' => 'Phone', 'website' => 'Website', 'booking_com' => 'Booking.com', 'agent' => 'Travel agent', 'corporate' => 'Corporate / NGO'] as $v => $l)
                            <option value="{{ $v }}" @selected(old('source', $r->source) === $v)>{{ $l }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label>Special requests</label><textarea name="special_requests">{{ old('special_requests', $r->special_requests) }}</textarea></div>
            </div>

            @include('partials.bill-to', ['orgId' => $r->organization_id, 'credit' => $r->on_credit])
        </div>
    </div>
    <div class="submit-bar">
        <a class="btn btn-light" href="{{ route('reservations.show', $r) }}">Cancel</a>
        <button class="btn" type="submit">Save changes</button>
    </div>
</form>
@endsection
