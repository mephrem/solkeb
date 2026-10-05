<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.print-head')
    <title>Credit invoice {{ $r->credit_invoice_no ?? $r->code }}</title>
</head>
<body>
<div class="doc">
    <div class="no-print actions" style="margin-bottom:1rem">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn btn-light" href="{{ route('reservations.show', $r) }}">Back to booking</a>
        @if ($r->status === 'checked_out')<a class="btn btn-light" href="{{ route('organizations.payment-request', ['organization' => $org, 'ids' => [$r->id]]) }}">Payment request letter</a>@endif
    </div>

    @php $due = $r->creditDueDate(); @endphp
    @include('partials.letterhead', ['right' => new \Illuminate\Support\HtmlString(
        '<h1 style="margin:0">'.($r->credit_invoice_no ? 'Credit invoice' : 'Credit invoice (draft)').'</h1>'
        .'<div><strong>'.e($r->credit_invoice_no ?? 'Issued at check-out').'</strong></div>'
        .'<div class="small">Booking '.e($r->code).'</div>'
        .($r->credit_invoiced_at ? '<div class="small">Date: '.$r->credit_invoiced_at->format('d M Y').'</div>' : '')
        .($due ? '<div class="small"><strong>Payment due: '.$due->format('d M Y').'</strong></div>' : ''))])

    <div class="inv-parties">
        <div>
            <div class="small muted">Bill to</div>
            <strong>{{ $org->name }}</strong>
            @if ($org->contact_person)<br>Attn: {{ $org->contact_person }}{{ $org->contact_title ? ', '.$org->contact_title : '' }}@endif
            @if ($org->address)<br>{{ $org->address }}@endif
            @if ($org->phone)<br>{{ $org->phone }}@endif
            @if ($org->tin)<br>TIN: <strong>{{ $org->tin }}</strong>@endif
        </div>
        <div class="right">
            <div class="small muted">Stay</div>
            @foreach ($r->rooms as $room)
                Room {{ $room->number }} ({{ $room->roomType->name }}) · {{ ($occupants[$room->pivot->guest_id] ?? $r->guest)->full_name }}<br>
            @endforeach
            {{ $r->check_in_date->format('d M Y') }} → {{ $r->check_out_date->format('d M Y') }} ({{ $r->nights() }} night{{ $r->nights() == 1 ? '' : 's' }})
        </div>
    </div>

    <table>
        <thead><tr><th>Date</th><th>Description</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Amount (ETB)</th></tr></thead>
        <tbody>
        @forelse ($r->charges as $c)
            <tr>
                <td class="nowrap">{{ $c->charge_date->format('d M') }}</td>
                <td>{{ $c->description }}</td>
                <td class="right">{{ rtrim(rtrim(number_format($c->quantity, 2), '0'), '.') }}</td>
                <td class="right">{{ number_format($c->unit_price, 2) }}</td>
                <td class="right">{{ number_format($c->amount, 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="empty">No charges.</td></tr>
        @endforelse
        </tbody>
    </table>

    <div style="display:flex;justify-content:flex-end;margin-top:1rem">
        <div class="total-box" style="min-width:300px">
            <div class="total-row"><span>Total (VAT included)</span><span>{{ number_format($total, 2) }}</span></div>
            <div class="total-row small muted"><span>of which VAT {{ rtrim(rtrim(number_format($vatPercent, 2), '0'), '.') }}%</span><span>{{ number_format($vatAmount, 2) }}</span></div>
            <div class="total-row"><span>Already paid</span><span>{{ number_format($paid, 2) }}</span></div>
            <div class="total-row big"><span>Amount due</span><span>{{ number_format(max(0, $balance), 2) }} ETB</span></div>
        </div>
    </div>

    @include('partials.pay-to')

    <div class="sign"><div>Prepared by: {{ auth()->user()->name }}</div><div>Approved by (stamp and signature)</div></div>
    <p class="small muted" style="text-align:center;margin-top:2rem">{{ \App\Models\Setting::get('invoice_footer') ?: 'Thank you for staying with us.' }}</p>
</div>
</body>
</html>
