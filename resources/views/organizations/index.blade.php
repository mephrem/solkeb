@extends('layouts.app')
@section('title', 'Organisations')
@section('content')
@php $total = $owed->flatten()->sum(fn ($r) => $r->balance()); @endphp
<div class="page-head">
    <div><h1>Organisations</h1><div class="muted">Companies, NGOs and offices that book rooms. Those allowed credit pay after their guests leave.</div></div>
    @if (auth()->user()->hasRole('admin','manager'))<a class="btn" href="{{ route('organizations.create') }}">+ Organisation</a>@endif
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Owed on credit</div><div class="stat-value">{{ number_format($total) }}</div><div class="stat-note">ETB, all organisations</div></div>
    <div class="stat"><div class="stat-label">Unpaid stays</div><div class="stat-value">{{ $owed->flatten()->count() }}</div><div class="stat-note">credit bookings with a balance</div></div>
</div>

<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="organisations">
        <thead><tr><th>Organisation</th><th>Contact</th><th>Credit</th><th class="right">Bookings</th><th class="right">Unpaid stays</th><th>Oldest unpaid</th><th class="right">Owed (ETB)</th></tr></thead>
        <tbody>
        @forelse ($orgs as $o)
            @php $list = $owed[$o->id]; $oldest = $list->where('status', 'checked_out')->sortBy('credit_invoiced_at')->first(); @endphp
            <tr>
                <td><a href="{{ route('organizations.show', $o) }}"><strong>{{ $o->name }}</strong></a>@unless ($o->is_active) <span class="badge">Hidden</span>@endunless</td>
                <td class="small">{{ $o->contact_person }}@if ($o->phone)<div class="muted">{{ $o->phone }}</div>@endif</td>
                <td>@if ($o->credit_allowed)<span class="badge b-available">{{ $o->credit_days }} days</span>@else<span class="badge">No</span>@endif</td>
                <td class="right">{{ $o->reservations_count }}</td>
                <td class="right">{{ $list->count() ?: '–' }}</td>
                <td class="small {{ $oldest && $oldest->creditDueDate()?->isPast() ? 'owe' : '' }}" data-order="{{ $oldest?->credit_invoiced_at?->toDateString() }}">
                    @if ($oldest){{ $oldest->credit_invoiced_at->format('d M Y') }}@if ($oldest->creditDueDate()?->isPast()) · overdue @endif @else – @endif</td>
                <td class="right {{ $list->isNotEmpty() ? 'owe' : '' }}"><strong>{{ number_format($list->sum(fn ($r) => $r->balance()), 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No organisations yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
