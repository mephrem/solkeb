@extends('layouts.app')
@section('title', 'Staff accounts')
@section('content')
<div class="page-head">
    <div><h1>Staff accounts</h1><div class="muted">Who can log in, and what they can do</div></div>
    <a class="btn" href="{{ route('users.create') }}">+ Add staff</a>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="staff">
        <thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @foreach ($users as $u)
            <tr @unless ($u->is_active) style="opacity:.55" @endunless>
                <td><strong>{{ $u->name }}</strong> @if ($u->is(auth()->user()))<span class="small muted">(you)</span>@endif</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->phone }}</td>
                <td>{{ ucfirst($u->role) }}</td>
                <td>@if ($u->is_active)<span class="badge b-available">Active</span>@else<span class="badge">Disabled</span>@endif</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('users.edit', $u) }}">Edit</a>
                    @include('partials.delete', ['action' => route('users.destroy', $u), 'name' => $u->name,
                        'text' => 'This login will be removed.',
                        'block' => $u->is(auth()->user()) ? 'You cannot delete your own account.'
                            : ($u->hasHistory() ? "{$u->name} has handled bookings or payments, and their name must stay on those records. Edit the account and untick \"Can log in\" instead." : null)])
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
<div class="card small">
    <h3>What each role can do</h3>
    <dl class="dl">
        <dt>Admin</dt><dd>Everything, including staff accounts and hotel settings</dd>
        <dt>Manager</dt><dd>Front desk, discounts, refunds, removing bill lines, rooms, prices and services</dd>
        <dt>Receptionist</dt><dd>Bookings, check-in and check-out, guests, adding charges and payments</dd>
        <dt>Accountant</dt><dd>Views bookings and guests, records payments and refunds</dd>
    </dl>
</div>
@endsection
