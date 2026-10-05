@extends('layouts.app')
@section('title', 'Reservations')
@section('content')
<div class="page-head">
    <h1>Reservations</h1>
    <div class="actions">
        @if (auth()->user()->hasRole('admin','manager'))
            <a class="btn btn-light" href="{{ route('reservations.deleted') }}">Deleted bookings</a>
        @endif
        @if (auth()->user()->hasRole('admin','manager','receptionist'))
            <a class="btn" href="{{ route('reservations.create') }}">+ New booking</a>
        @endif
    </div>
</div>

<form class="filters" method="GET">
    <div>
        <label for="status">Status</label>
        <select id="status" name="status" onchange="this.form.submit()">
            <option value="">All bookings</option>
            @foreach (['confirmed','checked_in','checked_out','cancelled','no_show'] as $s)
                <option value="{{ $s }}" @selected($status === $s)>{{ \Illuminate\Support\Str::headline($s) }}</option>
            @endforeach
        </select>
    </div>
</form>

<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="reservations">
        <thead><tr><th>Code</th><th>Guest</th><th>Rooms</th><th>Dates</th><th>Status</th>
            <th title="Reserved by">Reserved</th><th title="Checked in by">Checked in</th><th title="Payment received by">Paid to</th><th title="Checked out by">Checked out</th></tr></thead>
        <tbody>
        @forelse ($reservations as $r)
            <tr class="{{ $r->departureState() ? 'row-due' : '' }}">
                <td class="nowrap"><a href="{{ route('reservations.show', $r) }}"><strong>{{ $r->code }}</strong></a></td>
                <td>{{ $r->guest->full_name }}
                    @if ($r->group_color)<div><span class="group-chip" style="--g: {{ $r->group_color }}; font-size:.68rem; padding:.05rem .4rem">{{ $r->groupName() }}</span></div>@else<div class="small muted">{{ $r->guest->phone }}</div>@endif</td>
                <td class="small">{{ $r->rooms->pluck('number')->join(', ') }}</td>
                <td class="nowrap small" data-order="{{ $r->check_in_date->toDateString() }}">{{ $r->check_in_date->format('d M') }} → {{ $r->check_out_date->format('d M') }}
                    @if ($r->departureLabel())<div><span class="badge b-due">{{ $r->departureLabel() }}</span></div>@endif</td>
                <td>@include('partials.status', ['s' => $r->status])</td>
                <td class="small">{{ $r->creator?->name ?? '—' }}</td>
                <td class="small">{{ $r->checkedInBy?->name ?? '—' }}</td>
                <td class="small">{{ $r->payments->where('type', '!=', 'refund')->map(fn ($p) => $p->receiver?->name)->filter()->unique()->join(', ') ?: '—' }}</td>
                <td class="small">{{ $r->checkedOutBy?->name ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">No reservations found.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@if ($more)<p class="small muted">Showing the 3,000 most recent bookings. Pick a status above to see older ones.</p>@endif
@endsection
