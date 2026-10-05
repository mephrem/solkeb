@extends('layouts.app')
@section('title', $guest->full_name)
@section('content')
@php $idTypes = ['kebele_id' => 'Kebele ID', 'national_id' => 'National ID (Fayda)', 'passport' => 'Passport', 'driving_license' => 'Driving licence', 'other' => 'Other']; @endphp
<div class="page-head">
    <h1>{{ $guest->full_name }}
        @if ($guest->is_vip) <span class="badge b-vip">VIP</span> @endif
        @if ($guest->is_blacklisted) <span class="badge b-cancelled">Blacklisted</span> @endif</h1>
    @if (auth()->user()->hasRole('admin','manager','receptionist'))
    <div class="actions">
        <a class="btn btn-light" href="{{ route('guests.edit', $guest) }}">Edit</a>
        @include('partials.delete', ['action' => route('guests.destroy', $guest), 'name' => $guest->full_name,
            'text' => 'The guest record will be removed.',
            'block' => $guest->reservations()->withTrashed()->exists() ? "{$guest->full_name} has booking history, which must be kept." : null])
        <a class="btn" href="{{ route('reservations.create', ['guest_id' => $guest->id]) }}">+ Book for this guest</a>
    </div>
    @endif
</div>
<div class="grid grid-main">
    <div class="card" style="padding:0">
        <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Stays</h2></div>
        <div class="table-wrap">
        <table class="datatable" data-page-length="10">
            <thead><tr><th>Code</th><th>Dates</th><th>Rooms</th><th>Status</th></tr></thead>
            <tbody>
            @forelse ($guest->reservations as $r)
                <tr>
                    <td><a href="{{ route('reservations.show', $r) }}">{{ $r->code }}</a></td>
                    <td class="nowrap" data-order="{{ $r->check_in_date->toDateString() }}">{{ $r->check_in_date->format('d M Y') }} → {{ $r->check_out_date->format('d M Y') }}</td>
                    <td>{{ $r->rooms->pluck('number')->join(', ') }}</td>
                    <td>@include('partials.status', ['s' => $r->status])</td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No stays yet.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    <div class="card">
        <h2>Details</h2>
        <dl class="dl">
            <dt>Phone</dt><dd>{{ $guest->phone }}</dd>
            <dt>Email</dt><dd>{{ $guest->email ?: '—' }}</dd>
            <dt>Gender</dt><dd>{{ $guest->gender ? ucfirst($guest->gender) : '—' }}</dd>
            <dt>Nationality</dt><dd>{{ $guest->nationality ?: '—' }}</dd>
            <dt>ID</dt><dd>{{ $guest->id_number ? ($idTypes[$guest->id_type] ?? '').' · '.$guest->id_number : '—' }}</dd>
            <dt>ID photos</dt><dd>@include('partials.id-photos', ['guest' => $guest])</dd>
            <dt>City</dt><dd>{{ $guest->city ?: '—' }}</dd>
            <dt>Company</dt><dd>{{ $guest->company ?: '—' }}</dd>
            <dt>TIN</dt><dd>{{ $guest->tin ?: '—' }}</dd>
        </dl>
        @if ($guest->notes)<p class="small" style="margin:.75rem 0 0"><strong>Notes:</strong> {{ $guest->notes }}</p>@endif
    </div>
</div>
@endsection
