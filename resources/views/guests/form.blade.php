@extends('layouts.app')
@section('title', $guest->exists ? 'Edit guest' : 'Add guest')
@section('content')
<div class="page-head"><h1>{{ $guest->exists ? 'Edit '.$guest->full_name : 'Add guest' }}</h1></div>
<form enctype="multipart/form-data" class="card" method="POST" action="{{ $guest->exists ? route('guests.update', $guest) : route('guests.store') }}" style="max-width:860px">
    @csrf
    @if ($guest->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>First name *</label><input type="text" name="first_name" value="{{ old('first_name', $guest->first_name) }}" required></div>
        <div class="field"><label>Father's name</label><input type="text" name="father_name" value="{{ old('father_name', $guest->father_name) }}"></div>
    </div>
    <div class="fields">
        <div class="field"><label>Phone *</label><input type="tel" name="phone" value="{{ old('phone', $guest->phone) }}" placeholder="0911223344 or +252…" required></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $guest->email) }}"></div>
        <div class="field"><label>Gender</label>
            <select name="gender"><option value="">—</option>
                <option value="male" @selected(old('gender', $guest->gender) === 'male')>Male</option>
                <option value="female" @selected(old('gender', $guest->gender) === 'female')>Female</option>
            </select></div>
    </div>
    <div class="fields">
        <div class="field"><label>ID type</label>
            <select name="id_type"><option value="">—</option>
                @foreach (\App\Models\Guest::ID_TYPES as $v => $l)
                    <option value="{{ $v }}" @selected(old('id_type', $guest->id_type) === $v)>{{ $l }}</option>
                @endforeach
            </select></div>
        <div class="field"><label>ID number</label><input type="text" name="id_number" value="{{ old('id_number', $guest->id_number) }}"></div>
    </div>
    <div class="fields">
        <div class="field"><label>ID front (photo)</label><input type="file" name="id_front" accept="image/*,application/pdf" data-compress></div>
        <div class="field"><label>ID back (photo)</label><input type="file" name="id_back" accept="image/*,application/pdf" data-compress></div>
        <div class="field">@if ($guest->exists)<label>Saved</label>@include('partials.id-photos', ['guest' => $guest])@endif</div>
        @include('partials.nationality', ['name' => 'nationality', 'value' => old('nationality', $guest->nationality)])
    </div>
    <div class="fields">
        <div class="field"><label>City</label><input type="text" name="city" value="{{ old('city', $guest->city) }}"></div>
    </div>
    <div class="field"><label>Notes (preferences, allergies…)</label><textarea name="notes">{{ old('notes', $guest->notes) }}</textarea></div>
    <div class="actions" style="margin-bottom:1rem">
        <label class="check"><input type="checkbox" name="is_vip" value="1" @checked(old('is_vip', $guest->is_vip))> VIP</label>
        @if (auth()->user()->hasRole('admin','manager'))
            <label class="check"><input type="checkbox" name="is_blacklisted" value="1" @checked(old('is_blacklisted', $guest->is_blacklisted))> Blacklisted</label>
        @else
            <input type="hidden" name="is_blacklisted" value="{{ $guest->is_blacklisted ? 1 : 0 }}">
        @endif
    </div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ $guest->exists ? route('guests.show', $guest) : route('guests.index') }}">Cancel</a>
    </div>
</form>
@endsection
