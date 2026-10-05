@extends('layouts.app')
@section('title', 'My activity')
@section('content')
@php $range = $from->format('d M Y').($from->eq($to) ? '' : ' – '.$to->format('d M Y')); @endphp
<div class="page-head">
    <div><h1>My activity</h1><div class="muted">{{ auth()->user()->name }} · {{ $range }}</div></div>
    <div class="actions no-print"><button class="btn" type="button" onclick="window.print()">Print</button></div>
</div>

<form class="filters card no-print" method="GET" style="padding:.9rem 1.1rem">
    <div><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}" max="{{ today()->toDateString() }}"></div>
    <div><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}" max="{{ today()->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show</button></div>
    <div class="actions" style="align-self:end">
        @foreach (['Today' => [today(), today()], 'Yesterday' => [today()->subDay(), today()->subDay()], 'This week' => [today()->startOfWeek(), today()], 'This month' => [today()->startOfMonth(), today()]] as $label => [$a, $b])
            <a class="btn btn-sm {{ $from->isSameDay($a) && $to->isSameDay($b) ? '' : 'btn-light' }}" href="{{ route('my-activity', ['from' => $a->toDateString(), 'to' => $b->toDateString()]) }}">{{ $label }}</a>
        @endforeach
    </div>
</form>

<div class="stats">
    <div class="stat"><div class="stat-label">Rooms reserved</div><div class="stat-value">{{ $counts['rooms'] }}</div><div class="stat-note">in {{ $counts['reserved'] }} booking{{ $counts['reserved'] == 1 ? '' : 's' }}</div></div>
    <div class="stat"><div class="stat-label">Checked in</div><div class="stat-value">{{ $counts['checked_in'] }}</div><div class="stat-note">bookings</div></div>
    <div class="stat"><div class="stat-label">Checked out</div><div class="stat-value">{{ $counts['checked_out'] }}</div><div class="stat-note">bookings</div></div>
    <div class="stat"><div class="stat-label">Cash received</div><div class="stat-value">{{ number_format($cash) }}</div><div class="stat-note">ETB to hand over</div></div>
    <div class="stat"><div class="stat-label">Total received</div><div class="stat-value">{{ number_format($total) }}</div><div class="stat-note">ETB · {{ $counts['payments'] }} payments</div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Money I received</h2><span class="small muted">cash to hand over, and what went into each account</span></div>
    @if ($byAccount->isEmpty())
        <p class="empty">No payments received.</p>
    @else
    <table>
        <tbody>
        @foreach ($byAccount as $label => $amount)
            <tr><td>{{ $label }}</td><td class="right">{{ number_format($amount, 2) }}</td></tr>
        @endforeach
        </tbody>
        <tfoot><tr><td>Total</td><td class="right">{{ number_format($total, 2) }}</td></tr></tfoot>
    </table>
    @endif
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Payments</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-export="my-payments">
        <thead><tr><th>Time</th><th>Booking</th><th>Guest</th><th>Type</th><th>Received into</th><th>Reference</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td class="nowrap" data-order="{{ $p->paid_at->format('Y-m-d H:i:s') }}">{{ $p->paid_at->format($from->eq($to) ? 'H:i' : 'd M H:i') }}</td>
                <td class="nowrap"><a href="{{ route('reservations.show', $p->reservation) }}">{{ $p->reservation->code }}</a></td>
                <td>{{ $p->reservation->guest->full_name }}</td>
                <td>{{ ucfirst($p->type) }}</td>
                <td>{{ $p->channel() }}@if ($p->payer_name)<div class="small muted">paid by {{ $p->payer_name }}</div>@endif</td>
                <td class="small">{{ $p->reference }}</td>
                <td class="right {{ $p->type === 'refund' ? 'owe' : '' }}">{{ $p->type === 'refund' ? '−' : '' }}{{ number_format($p->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No payments received.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Everything I did</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-export="my-activity" data-page-length="50">
        <thead><tr><th>When</th><th>What</th><th>Booking</th><th>Guest</th><th>Details</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse ($activities as $a)
            <tr>
                <td class="nowrap" data-order="{{ $a->created_at->format('Y-m-d H:i:s') }}">{{ $a->created_at->format($from->eq($to) ? 'H:i' : 'd M H:i') }}</td>
                <td class="nowrap">{{ $a->label() }}</td>
                <td class="nowrap">
                    @if ($a->reservation && ! $a->reservation->trashed())<a href="{{ route('reservations.show', $a->reservation) }}">{{ $a->reservation->code }}</a>
                    @else{{ $a->reservation?->code ?? '—' }}@endif</td>
                <td>{{ $a->reservation?->guest?->full_name }}</td>
                <td class="small">{{ $a->details }}</td>
                <td class="right nowrap">{{ $a->amount !== null ? number_format($a->amount, 2) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">Nothing done in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
