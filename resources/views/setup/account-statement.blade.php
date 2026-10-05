@extends('layouts.app')
@section('title', $account->provider.' '.$account->account_number)
@section('content')
<div class="page-head">
    <div><h1>{{ $account->provider }} · {{ $account->account_number }}</h1>
        <div class="muted">{{ $account->account_name }} · payments received {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div></div>
    <div class="actions no-print">
        <a class="btn btn-light" href="{{ route('accounts.index') }}">All accounts</a>
        <button class="btn" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<form class="filters card no-print" method="GET" style="padding:.9rem 1.1rem">
    <div><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
    <div><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show</button></div>
</form>

<div class="stats">
    <div class="stat"><div class="stat-label">Received</div><div class="stat-value">{{ number_format($total, 2) }}</div><div class="stat-note">ETB, after refunds</div></div>
    <div class="stat"><div class="stat-label">Payments</div><div class="stat-value">{{ $payments->count() }}</div><div class="stat-note">in this period</div></div>
</div>

<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="account-statement">
        <thead><tr><th>Date</th><th>Transaction no.</th><th>Booking</th><th>Guest</th><th>Paid by</th><th>Type</th><th>Staff</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse ($payments as $p)
            <tr>
                <td class="nowrap">{{ $p->paid_at->format('d M Y H:i') }}</td>
                <td class="small">{{ $p->reference }}</td>
                <td>@if ($p->reservation)<a href="{{ route('reservations.show', $p->reservation) }}">{{ $p->reservation->code }}</a>@else<span class="muted">deleted</span>@endif</td>
                <td>{{ $p->reservation?->guest->full_name }}</td>
                <td class="small">{{ $p->payer_name }}</td>
                <td>{{ ucfirst($p->type) }}</td>
                <td class="small">{{ $p->receiver?->name }}</td>
                <td class="right {{ $p->type === 'refund' ? 'owe' : '' }}">{{ $p->type === 'refund' ? '−' : '' }}{{ number_format($p->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="8" class="empty">No payments into this account in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
