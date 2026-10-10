<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" type="image/png" href="{{ asset('images/solkeb-icon-64.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/solkeb-icon-180.png') }}">
    <title>Bill {{ $r->code }}</title>
    <link rel="stylesheet" href="{{ asset('css/solkeb.css') }}?v=17">
    <style>
        body { background: #fff; }
        .invoice { max-width: 780px; margin: 1.5rem auto; padding: 0 1rem; }
        .inv-head { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 4px solid var(--brand); padding-bottom: 1rem; margin-bottom: 1.25rem; gap: 1rem; }
        .inv-tagline { font-family: Georgia, 'Times New Roman', serif; font-style: italic; color: #2e3192; margin: .15rem 0 .35rem; }
        .inv-meta { text-align: right; }
        .inv-parties { display: flex; justify-content: space-between; gap: 1rem; margin-bottom: 1.25rem; flex-wrap: wrap; }
        .sign { display: flex; justify-content: space-between; margin-top: 3rem; gap: 2rem; }
        .sign div { flex: 1; border-top: 1px solid #94a3b8; padding-top: .3rem; font-size: .85rem; color: var(--muted); }
        @media screen and (max-width: 640px) { .inv-head { flex-wrap: wrap; } .inv-meta { text-align: left; } .inv-parties .right { text-align: left; } table { display: block; overflow-x: auto; } .sign { flex-direction: column; gap: 2.5rem; } }
    </style>
</head>
<body>
<div class="invoice">
    <div class="no-print actions" style="margin-bottom:1rem">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn btn-light" href="{{ route('reservations.show', $r) }}">Back</a>
    </div>

    <div class="inv-head">
        <div>
            <img src="{{ asset('images/solkeb-logo.png') }}" alt="{{ \App\Models\Setting::get('hotel_name', 'Solkeb Hotel') }}" style="height:44px;width:auto;display:block">
            <div class="inv-tagline">{{ \App\Models\Setting::get('tagline', 'Peaceful and Comfortable!') }}</div>
            <div class="muted">{{ \App\Models\Setting::get('city', 'Jijiga') }}, {{ \App\Models\Setting::get('region', 'Somali Region, Ethiopia') }}</div>
            @if (\App\Models\Setting::get('address'))<div class="muted">{{ \App\Models\Setting::get('address') }}</div>@endif
            @if (\App\Models\Setting::get('phone'))<div class="muted">Tel: {{ \App\Models\Setting::get('phone') }}@if (\App\Models\Setting::get('email')) · {{ \App\Models\Setting::get('email') }}@endif</div>@endif
            @if (\App\Models\Setting::get('tin'))<div class="muted">TIN: {{ \App\Models\Setting::get('tin') }}</div>@endif
        </div>
        <div class="inv-meta">
            <h1 style="margin:0">{{ $r->status === 'checked_out' ? 'Invoice' : 'Guest bill' }}</h1>
            <div><strong>{{ $r->code }}</strong></div>
            <div class="muted small">Printed {{ now()->format('d M Y H:i') }}</div>
        </div>
    </div>

    <div class="inv-parties">
        <div>
            <div class="small muted">Guest</div>
            <strong>{{ $r->guest->full_name }}</strong><br>
            {{ $r->guest->phone }}
            @if ($r->organization)<br>{{ $r->organization->name }}@if ($r->organization->tin)<br>TIN: <strong>{{ $r->organization->tin }}</strong>@endif
            @else
                @if ($r->guest->company)<br>{{ $r->guest->company }}@endif
                @if ($r->guest->tin)<br>TIN: <strong>{{ $r->guest->tin }}</strong>@endif
            @endif
        </div>
        <div class="right">
            <div class="small muted">Stay</div>
            Room {{ $r->rooms->pluck('number')->join(', ') }}<br>
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
            <div class="total-row"><span>Paid</span><span>{{ number_format($paid, 2) }}</span></div>
            <div class="total-row big"><span>{{ $balance > 0 ? 'Balance due' : 'Balance' }}</span><span>{{ number_format($balance, 2) }} ETB</span></div>
        </div>
    </div>

    @if ($r->payments->isNotEmpty())
        <h3 style="margin-top:1.5rem">Payments</h3>
        <table>
            <tbody>
            @foreach ($r->payments as $p)
                <tr>
                    <td>{{ $p->paid_at->format('d M Y H:i') }}</td>
                    <td>{{ ucfirst($p->type) }} · {{ $p->account?->provider ?? (\App\Models\Payment::METHODS[$p->method] ?? $p->method) }} {{ $p->reference ? '· '.$p->reference : '' }}</td>
                    <td class="right">{{ $p->type === 'refund' ? '−' : '' }}{{ number_format($p->amount, 2) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <div class="sign"><div>Guest signature</div><div>Cashier: {{ auth()->user()->name }}</div></div>
    <p class="small muted" style="text-align:center;margin-top:2rem">{{ \App\Models\Setting::get('invoice_footer') ?: 'Thank you for staying with us.' }}</p>
</div>
</body>
</html>
