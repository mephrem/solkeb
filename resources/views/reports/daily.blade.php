@extends('layouts.app')
@section('title', 'Night audit '.$date->format('d M Y'))
@section('content')
<div class="page-head">
    <div><h1>Night audit</h1><div class="muted">{{ $date->format('l, d F Y') }} · printed by {{ auth()->user()->name }} at {{ now()->format('H:i') }}</div></div>
    <div class="actions no-print">
        <a class="btn btn-light" href="{{ route('reports.daily', ['date' => $date->copy()->subDay()->toDateString()]) }}">‹ Previous day</a>
        @unless ($date->isToday())<a class="btn btn-light" href="{{ route('reports.daily', ['date' => $date->copy()->addDay()->toDateString()]) }}">Next day ›</a>@endunless
        <button class="btn" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Rooms occupied tonight</div><div class="stat-value">{{ $roomsSold }}</div><div class="stat-note">of {{ $sellable }} · {{ $sellable ? round($roomsSold / $sellable * 100) : 0 }}%</div></div>
    <div class="stat"><div class="stat-label">Checked in</div><div class="stat-value">{{ $arrivals->count() }}</div><div class="stat-note">bookings</div></div>
    <div class="stat"><div class="stat-label">Checked out</div><div class="stat-value">{{ $departures->count() }}</div><div class="stat-note">bookings</div></div>
    <div class="stat"><div class="stat-label">Collected</div><div class="stat-value">{{ number_format($collected) }}</div><div class="stat-note">ETB, after refunds</div></div>
    <div class="stat"><div class="stat-label">Services charged</div><div class="stat-value">{{ number_format($charges) }}</div><div class="stat-note">ETB food, laundry, etc.</div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Cash-up by staff member</h2><span class="small muted">What each person should hand over</span></div>
    @if ($byCashier->isEmpty())
        <p class="empty">No payments on this day.</p>
    @else
    <div class="table-wrap">
    <table>
        <thead><tr><th>Staff</th>@foreach ($byMethod as $m => $x)<th class="right">{{ \App\Models\Payment::METHODS[$m] ?? $m }}</th>@endforeach<th class="right">Total</th></tr></thead>
        <tbody>
        @foreach ($byCashier as $name => $methods)
            <tr>
                <td><strong>{{ $name }}</strong></td>
                @foreach ($byMethod as $m => $x)<td class="right">{{ isset($methods[$m]) ? number_format($methods[$m], 2) : '–' }}</td>@endforeach
                <td class="right"><strong>{{ number_format($methods->sum(), 2) }}</strong></td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total</td>@foreach ($byMethod as $x)<td class="right">{{ number_format($x, 2) }}</td>@endforeach<td class="right">{{ number_format($collected, 2) }}</td></tr></tfoot>
    </table>
    </div>
    @endif
</div>

<div class="card">
    <div class="card-head"><h2>Received into</h2><span class="small muted">Check each line against the bank or wallet statement</span></div>
    @if ($byAccount->isEmpty())
        <p class="empty">No payments on this day.</p>
    @else
    <table>
        <tbody>
        @foreach ($byAccount as $label => $amount)
            <tr><td>{{ $label }}</td><td class="right">{{ number_format($amount, 2) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total</td><td class="right">{{ number_format($collected, 2) }}</td></tr></tfoot>
    </table>
    @endif
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Payments</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-export="payments">
        <thead><tr><th>Time</th><th>Booking</th><th>Guest</th><th>Type</th><th>Received into</th><th>Reference</th><th>Staff</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td>{{ $p->paid_at->format('H:i') }}</td>
                <td><a href="{{ route('reservations.show', $p->reservation) }}">{{ $p->reservation->code }}</a></td>
                <td>{{ $p->reservation->guest->full_name }}</td>
                <td>{{ ucfirst($p->type) }}</td>
                <td>{{ $p->channel() }}@if ($p->payer_name)<div class="small muted">paid by {{ $p->payer_name }}</div>@endif</td>
                <td class="small">{{ $p->reference }}</td>
                <td class="small">{{ $p->receiver?->name }}</td>
                <td class="right {{ $p->type === 'refund' ? 'owe' : '' }}">{{ $p->type === 'refund' ? '−' : '' }}{{ number_format($p->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No payments.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="grid grid-2">
    @foreach ([['Checked in', $arrivals], ['Checked out', $departures]] as [$heading, $list])
    <div class="card">
        <h2>{{ $heading }}</h2>
        @forelse ($list as $r)
            <div class="total-row"><span><a href="{{ route('reservations.show', $r) }}">{{ $r->code }}</a> · {{ $r->guest->full_name }}</span><span>Room {{ $r->rooms->pluck('number')->join(', ') }}</span></div>
        @empty
            <p class="muted small" style="margin:0">None.</p>
        @endforelse
    </div>
    @endforeach
</div>

<div class="grid grid-2">
    <div class="card">
        <h2>In house tonight</h2>
        @forelse ($inHouse as $r)
            <div class="total-row"><span>Room {{ $r->rooms->pluck('number')->join(', ') }} · {{ $r->guest->full_name }}</span><span class="muted small">until {{ $r->check_out_date->format('d M') }}</span></div>
        @empty
            <p class="muted small" style="margin:0">No guests.</p>
        @endforelse
    </div>
    <div class="card">
        <h2>Unpaid balances (in house now)</h2>
        @forelse ($openBalances as $x)
            <div class="total-row"><span><a href="{{ route('reservations.show', $x['r']) }}">{{ $x['r']->code }}</a> · {{ $x['r']->guest->full_name }}</span><span class="owe">{{ number_format($x['balance'], 2) }}</span></div>
        @empty
            <p class="muted small" style="margin:0">All in-house bills are paid.</p>
        @endforelse
    </div>
</div>

@if ($deleted->isNotEmpty())
<div class="card" style="border-left:4px solid var(--red)">
    <h2>Deleted bookings</h2>
    @foreach ($deleted as $r)
        <div class="total-row"><span><strong>{{ $r->code }}</strong> · {{ $r->guest->full_name }} · by {{ $r->deleter?->name ?? '—' }} at {{ $r->deleted_at->format('H:i') }}</span><span class="small muted">{{ $r->delete_reason }}</span></div>
    @endforeach
</div>
@endif

@if ($noShows->isNotEmpty())
<div class="card">
    <h2>Cancelled / no-show</h2>
    @foreach ($noShows as $r)
        <div class="total-row"><span><a href="{{ route('reservations.show', $r) }}">{{ $r->code }}</a> · {{ $r->guest->full_name }}</span>@include('partials.status', ['s' => $r->status])</div>
    @endforeach
</div>
@endif

<div class="sign no-screen" style="display:flex;gap:2rem;margin-top:3rem">
    <div style="flex:1;border-top:1px solid #94a3b8;padding-top:.3rem" class="small muted">Prepared by</div>
    <div style="flex:1;border-top:1px solid #94a3b8;padding-top:.3rem" class="small muted">Checked by (manager)</div>
</div>
@endsection
