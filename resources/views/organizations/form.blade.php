@extends('layouts.app')
@section('title', $org->exists ? 'Edit organisation' : 'Add organisation')
@section('content')
<div class="page-head"><h1>{{ $org->exists ? 'Edit '.$org->name : 'Add organisation' }}</h1></div>
<form class="card" method="POST" action="{{ $org->exists ? route('organizations.update', $org) : route('organizations.store') }}" style="max-width:820px">
    @csrf @if ($org->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Name *</label><input type="text" name="name" value="{{ old('name', $org->name) }}" placeholder="e.g. UNICEF Jijiga Office" required>
            @error('name')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label>TIN</label><input type="text" name="tin" value="{{ old('tin', $org->tin) }}" maxlength="20"></div>
    </div>
    <div class="fields">
        <div class="field"><label>Contact person</label><input type="text" name="contact_person" value="{{ old('contact_person', $org->contact_person) }}" placeholder="Who the payment letter goes to"></div>
        <div class="field"><label>Their title</label><input type="text" name="contact_title" value="{{ old('contact_title', $org->contact_title) }}" placeholder="e.g. Finance Head"></div>
    </div>
    <div class="fields">
        <div class="field"><label>Phone</label><input type="text" name="phone" value="{{ old('phone', $org->phone) }}"></div>
        <div class="field"><label>Email</label><input type="email" name="email" value="{{ old('email', $org->email) }}"></div>
    </div>
    <div class="field"><label>Address</label><input type="text" name="address" value="{{ old('address', $org->address) }}" placeholder="e.g. Kebele 05, Jijiga"></div>

    <fieldset class="occupant" style="margin-top:.5rem">
        <legend>Credit</legend>
        <label class="check"><input type="checkbox" name="credit_allowed" value="1" @checked(old('credit_allowed', $org->credit_allowed))> Allowed to pay on credit (after the guest leaves, against a credit invoice and payment request letter)</label>
        <div class="field" style="max-width:240px;margin-top:.6rem"><label>Days to pay a credit invoice</label><input type="number" name="credit_days" min="0" max="365" value="{{ old('credit_days', $org->credit_days) }}" required></div>
    </fieldset>

    <div class="field"><label>Notes</label><input type="text" name="notes" value="{{ old('notes', $org->notes) }}" placeholder="e.g. Agreement signed March 2026, pays by CBE transfer"></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $org->is_active))> Active (can be chosen on new bookings)</label></div>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ $org->exists ? route('organizations.show', $org) : route('organizations.index') }}">Cancel</a>
    </div>
</form>
@endsection
