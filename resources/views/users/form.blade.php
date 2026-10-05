@extends('layouts.app')
@section('title', $user->exists ? 'Edit staff' : 'Add staff')
@section('content')
<div class="page-head"><h1>{{ $user->exists ? 'Edit '.$user->name : 'Add staff' }}</h1></div>
<form class="card" method="POST" action="{{ $user->exists ? route('users.update', $user) : route('users.store') }}" style="max-width:720px" autocomplete="off" @if ($user->exists && $user->is_active) data-confirm-if-unchecked="is_active" data-confirm-title="Disable {{ $user->name }}?" data-confirm="They will no longer be able to log in." data-confirm-button="Disable" data-confirm-danger @endif>
    @csrf @if ($user->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Full name *</label><input type="text" name="name" value="{{ old('name', $user->name) }}" required></div>
        <div class="field"><label>Phone</label><input type="tel" name="phone" value="{{ old('phone', $user->phone) }}"></div>
    </div>
    <div class="fields">
        <div class="field"><label>Email (used to log in) *</label><input type="email" name="email" value="{{ old('email', $user->email) }}" required></div>
        <div class="field"><label>Role *</label>
            <select name="role">@foreach (\App\Models\User::ROLES as $r)<option value="{{ $r }}" @selected(old('role', $user->role) === $r)>{{ ucfirst($r) }}</option>@endforeach</select></div>
    </div>
    <fieldset>
        <legend>{{ $user->exists ? 'Reset password' : 'Password' }}</legend>
        @if ($user->exists)<p class="small muted" style="margin-top:0">Leave empty to keep the current password.</p>@endif
        <div class="fields">
            <div class="field"><label>New password {{ $user->exists ? '' : '*' }}</label><input type="password" name="password" autocomplete="new-password" @required(! $user->exists)></div>
            <div class="field"><label>Repeat password</label><input type="password" name="password_confirmation" autocomplete="new-password"></div>
        </div>
        <p class="small muted" style="margin-top:0">At least 8 characters.</p>
    </fieldset>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> Can log in</label></div>
    <p class="small muted">When someone leaves, untick "Can log in" instead of deleting them. Their name stays on the bookings and payments they handled.</p>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ route('users.index') }}">Cancel</a>
    </div>
</form>
@endsection
