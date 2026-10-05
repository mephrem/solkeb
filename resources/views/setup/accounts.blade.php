@extends('layouts.app')
@section('title', 'Bank accounts')
@section('content')
<div class="page-head">
    <div><h1>Bank accounts &amp; wallets</h1><div class="muted">Where the hotel receives money. Staff choose one of these each time a guest pays by bank or mobile money.</div></div>
    <a class="btn" href="{{ route('accounts.create') }}">+ Account</a>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="accounts">
        <thead><tr><th>Bank / wallet</th><th>Account number</th><th>Account name</th><th>Type</th><th class="right">Payments</th><th class="right">This month (ETB)</th><th class="right">All time (ETB)</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($accounts as $a)
            @php $t = $totals[$a->id] ?? null; @endphp
            <tr>
                <td><a href="{{ route('accounts.show', $a) }}"><strong>{{ $a->provider }}</strong></a>@if ($a->notes)<div class="small muted">{{ $a->notes }}</div>@endif</td>
                <td class="nowrap">{{ $a->account_number }}</td>
                <td>{{ $a->account_name }}</td>
                <td>{{ \App\Models\PaymentAccount::KINDS[$a->kind] ?? $a->kind }}</td>
                <td class="right">{{ $t->payments ?? 0 }}</td>
                <td class="right">{{ number_format($t->month ?? 0, 2) }}</td>
                <td class="right">{{ number_format($t->total ?? 0, 2) }}</td>
                <td>@if ($a->is_active)<span class="badge b-available">Active</span>@else<span class="badge">Not used</span>@endif
                    @if ($a->id === $invoiceAccount?->id)<div><span class="badge" title="Printed under Please pay to">On invoices</span></div>@endif</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('accounts.show', $a) }}">Statement</a>
                    <a class="btn btn-sm btn-light" href="{{ route('accounts.edit', $a) }}">Edit</a>
                    @include('partials.delete', ['action' => route('accounts.destroy', $a), 'name' => $a->provider.' '.$a->account_number,
                        'text' => 'Staff will no longer be able to choose it.',
                        'block' => ($t->payments ?? 0) ? 'Payments were received into this account and must stay on record. Edit it and untick Active to stop using it.' : null])
                </td>
            </tr>
        @empty
            <tr><td colspan="9" class="empty">No accounts yet. Add each bank account and mobile wallet the hotel receives money into.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<p class="small muted">Cash received at reception this month: <strong>{{ number_format($cashMonth, 2) }} ETB</strong>.</p>
@endsection
