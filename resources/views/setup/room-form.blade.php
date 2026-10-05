@extends('layouts.app')
@section('title', $room->exists ? 'Edit room' : 'Add room')
@section('content')
<div class="page-head"><h1>{{ $room->exists ? 'Edit room '.$room->number : 'Add room' }}</h1></div>
<form class="card" method="POST" action="{{ $room->exists ? route('room-setup.update', $room) : route('room-setup.store') }}" style="max-width:720px">
    @csrf @if ($room->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Room number *</label><input type="text" name="number" value="{{ old('number', $room->number) }}" placeholder="e.g. 101" required></div>
        <div class="field"><label>Floor *</label><input type="number" min="0" name="floor" value="{{ old('floor', $room->floor) }}" required></div>
        <div class="field"><label>Type *</label>
            <select name="room_type_id" required>
                @foreach ($types as $t)<option value="{{ $t->id }}" @selected(old('room_type_id', $room->room_type_id) == $t->id)>{{ $t->name }} · {{ number_format($t->base_rate) }} ETB</option>@endforeach
            </select></div>
    </div>
    <div class="field"><label>Own price per night (ETB) <span class="muted">(leave empty to use the type's rate)</span></label>
        <input type="number" step="0.01" min="0" name="rate_override" value="{{ old('rate_override', $room->rate_override) }}"></div>
    <div class="field"><label>Notes</label><input type="text" name="notes" value="{{ old('notes', $room->notes) }}" placeholder="e.g. Balcony, city view"></div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ route('setup.rooms') }}">Cancel</a>
    </div>
</form>
@endsection
