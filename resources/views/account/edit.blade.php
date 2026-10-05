@extends('layouts.app')
@section('title', 'My account')
@section('content')
<div class="page-head"><div><h1>My account</h1><div class="muted">{{ auth()->user()->name }} · {{ auth()->user()->email }} · {{ ucfirst(auth()->user()->role) }}</div></div></div>
<form class="card" method="POST" action="{{ route('account.password') }}" style="max-width:480px">
    @csrf @method('PUT')
    <h2>Change password</h2>
    <div class="field"><label>Current password</label><input type="password" name="current_password" autocomplete="current-password" required></div>
    <div class="field"><label>New password</label><input type="password" name="password" autocomplete="new-password" required></div>
    <div class="field"><label>Repeat new password</label><input type="password" name="password_confirmation" autocomplete="new-password" required></div>
    <p class="small muted">At least 8 characters.</p>
    <button class="btn" type="submit">Change password</button>
</form>
@endsection
