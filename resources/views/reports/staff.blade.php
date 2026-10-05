@extends('layouts.app')
@section('title', 'Staff activity')
@section('content')
<div class="page-head">
    <div><h1>Staff activity</h1><div class="muted">{{ $from->format('d M Y') }}@unless ($from->eq($to)) – {{ $to->format('d M Y') }}@endunless · who reserved, checked in, checked out and received money</div></div>
    <div class="actions no-print">
        <a class="btn btn-light" href="{{ route('reports.index') }}">Reports</a>
        <button class="btn" type="button" onclick="window.print()">Print</button>
    </div>
</div>

<form class="filters card no-print" method="GET" style="padding:.9rem 1.1rem">
    <div><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
    <div><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show</button></div>
    <div class="actions" style="align-self:end">
        @foreach (['Today' => [today(), today()], 'Yesterday' => [today()->subDay(), today()->subDay()], 'This week' => [today()->startOfWeek(), today()], 'This month' => [today()->startOfMonth(), today()]] as $label => [$a, $b])
            <a class="btn btn-sm {{ $from->isSameDay($a) && $to->isSameDay($b) ? '' : 'btn-light' }}" href="{{ route('reports.staff', ['from' => $a->toDateString(), 'to' => $b->toDateString()]) }}">{{ $label }}</a>
        @endforeach
    </div>
</form>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Per staff member</h2><span class="small muted">money in ETB, after refunds they gave</span></div>
    <div class="table-wrap">
    <table>
        <thead><tr><th>Staff</th><th class="right">Reserved</th><th class="right">Checked in</th><th class="right">Checked out</th><th class="right">Payments</th>
            @foreach ($methods as $m)<th class="right">{{ \App\Models\Payment::METHODS[$m] ?? $m }}</th>@endforeach<th class="right">Total received</th></tr></thead>
        <tbody>
        @forelse ($rows as $row)
            <tr>
                <td><strong>{{ $row->user->name }}</strong><div class="small muted">{{ ucfirst($row->user->role) }}</div></td>
                <td class="right">{{ $row->reserved }}</td>
                <td class="right">{{ $row->checked_in }}</td>
                <td class="right">{{ $row->checked_out }}</td>
                <td class="right">{{ $row->payments }}</td>
                @foreach ($methods as $m)<td class="right">{{ $row->by_method[$m] ? number_format($row->by_method[$m], 2) : '–' }}</td>@endforeach
                <td class="right"><strong>{{ number_format($row->total, 2) }}</strong></td>
            </tr>
        @empty
            <tr><td colspan="{{ 6 + $methods->count() }}" class="empty">Nothing was done in this period.</td></tr>
        @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
        <tfoot><tr><td>Total</td>
            <td class="right">{{ $rows->sum('reserved') }}</td><td class="right">{{ $rows->sum('checked_in') }}</td><td class="right">{{ $rows->sum('checked_out') }}</td><td class="right">{{ $rows->sum('payments') }}</td>
            @foreach ($methods as $m)<td class="right">{{ number_format($rows->sum(fn ($r) => $r->by_method[$m]), 2) }}</td>@endforeach
            <td class="right">{{ number_format($rows->sum('total'), 2) }}</td></tr></tfoot>
        @endif
    </table>
    </div>
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Everything that was done</h2><span class="small muted">type a name in Search to see one person's work</span></div>
    <div class="table-wrap">
    <table class="datatable" data-export="staff-activity" data-page-length="50">
        <thead><tr><th>When</th><th>Staff</th><th>What</th><th>Booking</th><th>Guest</th><th>Details</th><th class="right">Amount</th></tr></thead>
        <tbody>
        @forelse ($activities as $a)
            <tr>
                <td class="nowrap" data-order="{{ $a->created_at->format('Y-m-d H:i:s') }}">{{ $a->created_at->format('d M H:i') }}</td>
                <td class="nowrap"><strong>{{ $a->user?->name ?? '—' }}</strong></td>
                <td class="nowrap">{{ $a->label() }}</td>
                <td class="nowrap">
                    @if ($a->reservation && ! $a->reservation->trashed())<a href="{{ route('reservations.show', $a->reservation) }}">{{ $a->reservation->code }}</a>
                    @else{{ $a->reservation?->code ?? '—' }}@endif</td>
                <td>{{ $a->reservation?->guest?->full_name }}</td>
                <td class="small">{{ $a->details }}</td>
                <td class="right nowrap">{{ $a->amount !== null ? number_format($a->amount, 2) : '' }}</td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">Nothing was done in this period.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
