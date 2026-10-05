<!DOCTYPE html>
<html lang="en">
<head>
    @include('partials.print-head')
    <title>Payment request · {{ $org->name }}</title>
</head>
<body>
@php $hotel = \App\Models\Setting::get('hotel_name', 'Solkeb Hotel'); $sum = $items->sum('balance'); @endphp
<div class="doc letter">
    <div class="no-print actions" style="margin-bottom:1rem">
        <button class="btn" onclick="window.print()">Print</button>
        <a class="btn btn-light" href="{{ route('organizations.show', $org) }}">Back to {{ $org->name }}</a>
    </div>

    @include('partials.letterhead', ['right' => new \Illuminate\Support\HtmlString(
        '<div class="small">Ref. No: <strong>'.e($reference).'</strong></div><div class="small">Date: '.now()->format('d M Y').'</div>')])

    <p>
        To: <strong>{{ $org->name }}</strong><br>
        @if ($org->contact_person)Attn: {{ $org->contact_person }}{{ $org->contact_title ? ', '.$org->contact_title : '' }}<br>@endif
        @if ($org->address){{ $org->address }}<br>@endif
        @if ($org->tin)TIN: {{ $org->tin }}@endif
    </p>

    <p><strong>Subject: Request for payment of accommodation services</strong></p>

    @if ($items->isEmpty())
        <p class="alert alert-error no-print">There are no unpaid credit invoices to include in this letter.</p>
    @else
        <p>Dear Sir / Madam,</p>
        <p>
            We thank you for choosing {{ $hotel }}. As agreed, the accommodation and services provided to your staff and guests listed below
            were given on credit. We kindly request you to settle the outstanding amount of
            <strong>{{ number_format($sum, 2) }} ETB</strong>
            ({{ $items->count() }} invoice{{ $items->count() == 1 ? '' : 's' }}).
        </p>

        <table>
            <thead><tr><th>Invoice no.</th><th>Guest</th><th>Room</th><th>Stay</th><th class="right">Total</th><th class="right">Paid</th><th class="right">Due (ETB)</th></tr></thead>
            <tbody>
            @foreach ($items as $i)
                <tr>
                    <td class="nowrap">{{ $i->r->credit_invoice_no }}</td>
                    <td>@foreach ($i->stays as $s){{ $s->guest }}@if (! $loop->last)<br>@endif @endforeach</td>
                    <td>@foreach ($i->stays as $s){{ $s->room }}@if (! $loop->last)<br>@endif @endforeach</td>
                    <td class="nowrap">{{ $i->r->check_in_date->format('d M') }} – {{ $i->r->check_out_date->format('d M Y') }} ({{ $i->r->nights() }} n.)</td>
                    <td class="right">{{ number_format($i->total, 2) }}</td>
                    <td class="right">{{ number_format($i->paid, 2) }}</td>
                    <td class="right"><strong>{{ number_format($i->balance, 2) }}</strong></td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td colspan="6">Total due</td><td class="right">{{ number_format($sum, 2) }}</td></tr></tfoot>
        </table>

        <p>The credit invoices are attached. Prices include VAT. Please arrange payment within {{ $org->credit_days }} days of the invoice date.</p>

        @include('partials.pay-to')

        <p>We appreciate your continued partnership and look forward to serving you again.</p>
        <p>Sincerely,</p>
        <div class="sign" style="margin-top:2.5rem"><div>Name and signature</div><div>Manager, {{ $hotel }} (stamp)</div></div>
    @endif
</div>
</body>
</html>
