@extends('layouts.app')
@section('title', $org->name)
@section('content')
<div class="page-head">
    <div>
        <h1>{{ $org->name }} @if ($org->credit_allowed)<span class="badge b-available" style="vertical-align:middle">Credit · {{ $org->credit_days }} days</span>@endif</h1>
        <div class="muted">
            {{ collect([$org->contact_person ? $org->contact_person.($org->contact_title ? ', '.$org->contact_title : '') : null, $org->phone, $org->email, $org->address, $org->tin ? 'TIN '.$org->tin : null])->filter()->join(' · ') ?: 'No contact details yet' }}
        </div>
    </div>
    <div class="actions">
        @if ($unpaid->where('status', 'checked_out')->isNotEmpty())
            <a class="btn" href="{{ route('organizations.payment-request', $org) }}" target="_blank">Payment request letter</a>
        @endif
        @if (auth()->user()->hasRole('admin','manager'))<a class="btn btn-light" href="{{ route('organizations.edit', $org) }}">Edit</a>@endif
    </div>
</div>

<div class="stats">
    <div class="stat"><div class="stat-label">Owed</div><div class="stat-value {{ $owed > 0 ? 'owe' : '' }}">{{ number_format($owed, 2) }}</div><div class="stat-note">ETB on credit</div></div>
    <div class="stat"><div class="stat-label">Unpaid stays</div><div class="stat-value">{{ $unpaid->count() }}</div><div class="stat-note">{{ $unpaid->where('status', 'checked_in')->count() }} still in house</div></div>
    <div class="stat"><div class="stat-label">Bookings</div><div class="stat-value">{{ $bookings->count() }}</div><div class="stat-note">all time</div></div>
</div>

@php $finished = $unpaid->where('status', 'checked_out')->sortBy('credit_invoiced_at'); @endphp
@if ($finished->isNotEmpty())
<div class="card" style="border-left:4px solid var(--green)">
    <h2>Receive payment</h2>
    <p class="small muted" style="margin-top:0">When {{ $org->name }} pays, record the whole transfer here once. It is shared out over the ticked invoices, oldest first.</p>
    <form method="POST" action="{{ route('organizations.payments', $org) }}" data-confirm-payment>
        @csrf
        <div class="fields">
            <div class="field"><label>Amount received (ETB)</label><input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', number_format($finished->sum(fn ($r) => $r->balance()), 2, '.', '')) }}" required>
                @error('amount')<div class="error-text">{{ $message }}</div>@enderror</div>
            @include('partials.received-into', ['name' => 'received_into', 'method' => 'method'])
            <div class="field"><label>Transaction / receipt no.</label><input type="text" name="reference" value="{{ old('reference') }}">
                @error('reference')<div class="error-text">{{ $message }}</div>@enderror</div>
            @include('partials.paid-by', ['name' => 'payer_name', 'org' => $org, 'default' => $org->name])
        </div>
        <div class="small" style="margin-bottom:.4rem"><strong>Covers these invoices</strong></div>
        @foreach ($finished as $r)
            <label class="check" style="display:block"><input type="checkbox" name="ids[]" value="{{ $r->id }}" @checked(! old('ids') || in_array($r->id, old('ids', [])))>
                {{ $r->credit_invoice_no }} · {{ $r->guest->full_name }} · {{ $r->check_in_date->format('d M') }} → {{ $r->check_out_date->format('d M') }} · <strong>{{ number_format($r->balance(), 2) }} ETB</strong></label>
        @endforeach
        @error('ids')<div class="error-text">{{ $message }}</div>@enderror
        <button class="btn btn-sm btn-green" type="submit" style="margin-top:.75rem">Record payment</button>
    </form>
</div>
@endif

<div class="card">
    <div class="card-head"><h2>Unpaid credit invoices</h2><span class="small muted">tick some to ask for those only, or leave all unticked for everything owed</span></div>
    @if ($unpaid->isEmpty())
        <p class="empty">Nothing owed.</p>
    @else
    <form method="GET" action="{{ route('organizations.payment-request', $org) }}" target="_blank">
        <div class="table-wrap">
        <table>
            <thead><tr><th></th><th>Invoice</th><th>Booking</th><th>Guest</th><th>Rooms</th><th>Stay</th><th>Due</th><th class="right">Total</th><th class="right">Paid</th><th class="right">Owed</th><th></th></tr></thead>
            <tbody>
            @foreach ($unpaid as $r)
                @php $due = $r->creditDueDate(); @endphp
                <tr>
                    <td>@if ($r->status === 'checked_out')<input type="checkbox" name="ids[]" value="{{ $r->id }}" aria-label="Include {{ $r->code }}">@endif</td>
                    <td class="nowrap">{{ $r->credit_invoice_no ?? 'in house' }}</td>
                    <td class="nowrap"><a href="{{ route('reservations.show', $r) }}">{{ $r->code }}</a></td>
                    <td>{{ $r->guest->full_name }}</td>
                    <td class="small">{{ $r->rooms->pluck('number')->join(', ') }}</td>
                    <td class="small nowrap">{{ $r->check_in_date->format('d M') }} → {{ $r->check_out_date->format('d M Y') }}</td>
                    <td class="small nowrap {{ $due?->isPast() ? 'owe' : '' }}">{{ $due ? $due->format('d M Y').($due->isPast() ? ' · overdue' : '') : '–' }}</td>
                    <td class="right">{{ number_format($r->totalCharges(), 2) }}</td>
                    <td class="right">{{ number_format($r->totalPaid(), 2) }}</td>
                    <td class="right owe"><strong>{{ number_format($r->balance(), 2) }}</strong></td>
                    <td class="right nowrap"><a class="btn btn-sm btn-light" href="{{ route('reservations.credit-invoice', $r) }}" target="_blank">Credit invoice</a></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="9">Total owed</td><td class="right">{{ number_format($owed, 2) }}</td><td></td></tr></tfoot>
        </table>
        </div>
        <button class="btn btn-sm" type="submit" style="margin-top:.75rem">Payment request letter for ticked / all</button>
    </form>
    <p class="small muted">To record what the organisation paid, use Receive payment above. Stays still in house can be paid once the guest has checked out.</p>
    @endif
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>All bookings</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-export="organisation-bookings">
        <thead><tr><th>Booking</th><th>Guest</th><th>Rooms</th><th>Dates</th><th>Status</th><th>Billing</th></tr></thead>
        <tbody>
        @forelse ($bookings as $r)
            <tr>
                <td class="nowrap"><a href="{{ route('reservations.show', $r) }}">{{ $r->code }}</a></td>
                <td>{{ $r->guest->full_name }}</td>
                <td class="small">{{ $r->rooms->pluck('number')->join(', ') }}</td>
                <td class="small nowrap" data-order="{{ $r->check_in_date->toDateString() }}">{{ $r->check_in_date->format('d M') }} → {{ $r->check_out_date->format('d M Y') }}</td>
                <td>@include('partials.status', ['s' => $r->status])</td>
                <td class="small">{{ $r->on_credit ? 'On credit' : 'Pays at hotel' }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No bookings yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
