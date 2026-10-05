@extends('layouts.app')
@section('title', $type->exists ? 'Edit room type' : 'Add room type')
@section('content')
<div class="page-head"><h1>{{ $type->exists ? 'Edit '.$type->name : 'Add room type' }}</h1></div>
<form class="card" method="POST" action="{{ $type->exists ? route('room-types.update', $type) : route('room-types.store') }}" style="max-width:720px">
    @csrf @if ($type->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Name *</label><input type="text" name="name" value="{{ old('name', $type->name) }}" placeholder="e.g. Deluxe" required></div>
        <div class="field"><label>Rate per night (ETB) *</label><input type="number" step="0.01" min="0" name="base_rate" value="{{ old('base_rate', $type->base_rate) }}" required></div>
    </div>
    <div class="fields">
        <div class="field"><label>Max adults</label><input type="number" min="1" name="max_adults" value="{{ old('max_adults', $type->max_adults) }}"></div>
        <div class="field"><label>Max children</label><input type="number" min="0" name="max_children" value="{{ old('max_children', $type->max_children) }}"></div>
    </div>
    <div class="field"><label>Description</label><input type="text" name="description" value="{{ old('description', $type->description) }}"></div>
    <div class="field"><label>Amenities <span class="muted">(separate with commas)</span></label>
        <input type="text" name="amenities" value="{{ old('amenities', implode(', ', $type->amenities ?? [])) }}" placeholder="WiFi, TV, Air conditioning, Hot water"></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $type->is_active))> Active</label></div>
    @if ($type->exists)<p class="small muted">Changing the rate affects new bookings only. Existing bookings keep the rate they were booked at.</p>@endif
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ route('setup.rooms') }}">Cancel</a>
    </div>
</form>
@endsection
