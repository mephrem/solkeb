@extends('layouts.app')
@section('title', 'Dashboard')
@section('content')
<div class="page-head">
    <div>
        <h1>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ strtok(auth()->user()->name, ' ') }}</h1>
        <div class="muted">{{ now()->format('l, j F Y') }}</div>
    </div>
</div>

<div class="stats">
    <div class="stat">
        <div class="stat-label">Occupancy</div>
        <div class="stat-value">{{ $occupancy }}%</div>
        <div class="stat-note">{{ $occupied }} of {{ $sellable }} rooms</div>
    </div>
    <div class="stat">
        <div class="stat-label">Arrivals today</div>
        <div class="stat-value">{{ $arrivals->count() }}</div>
        <div class="stat-note">waiting to check in</div>
    </div>
    <div class="stat">
        <div class="stat-label">Departures today</div>
        <div class="stat-value">{{ $departures->count() }}</div>
        <div class="stat-note">{{ $inHouse }} bookings in house</div>
    </div>
    <div class="stat">
        <div class="stat-label">Rooms to clean</div>
        <div class="stat-value">{{ $dirtyRooms }}</div>
        <div class="stat-note"><a href="{{ route('rooms.index') }}">see the room board</a></div>
    </div>
    @if (auth()->user()->hasRole('admin','manager','accountant','receptionist'))
    <div class="stat">
        <div class="stat-label">Collected today</div>
        <div class="stat-value">{{ number_format($takenToday) }}</div>
        <div class="stat-note">ETB, all payment methods</div>
    </div>
    @endif
</div>

@if ($overdue->isNotEmpty())
    <div class="alert alert-error">
        <strong>Overdue check-outs:</strong>
        @foreach ($overdue as $r)
            <a href="{{ route('reservations.show', $r) }}">{{ $r->guest->full_name }} (Room {{ $r->rooms->pluck('number')->join(', ') }})</a>@if (! $loop->last), @endif
        @endforeach
    </div>
@endif

<div class="grid grid-2">
    @foreach ([['Arrivals today', $arrivals, 'No arrivals expected today.'], ['Departures today', $departures, 'No departures today.']] as [$heading, $list, $emptyText])
    <div class="card">
        <div class="card-head"><h2>{{ $heading }}</h2><span class="badge">{{ $list->count() }}</span></div>
        <div class="table-wrap">
        <table>
            <thead><tr><th>Guest</th><th>Rooms</th><th>Nights</th><th></th></tr></thead>
            <tbody>
            @forelse ($list as $r)
                <tr class="{{ $r->departureState() ? 'row-due' : '' }}">
                    <td>
                        <strong>{{ $r->guest->full_name }}</strong>
                        @if ($r->guest->is_vip) <span class="badge b-vip">VIP</span> @endif
                        @if ($r->departureLabel()) <span class="badge b-due">{{ $r->departureLabel() }}</span> @endif
                        <div class="small muted">{{ $r->guest->phone }}</div>
                    </td>
                    <td>{{ $r->rooms->pluck('number')->join(', ') }}</td>
                    <td>{{ $r->nights() }}</td>
                    <td class="right"><a class="btn btn-sm btn-light" href="{{ route('reservations.show', $r) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">{{ $emptyText }}</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
    @endforeach
</div>
@endsection
