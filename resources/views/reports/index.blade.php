@extends('layouts.app')
@section('title', 'Reports')
@section('content')
@php
    $t = $totals;
    $money = fn ($v) => number_format($v, 0);
    // ---- Revenue chart (single series, so no legend: the title names it) ----
    $W = 820; $H = 240; $padL = 64; $padR = 12; $padT = 22; $padB = 28;
    $max = max(1, max(array_column($chart, 'value') ?: [0]));
    $step = 10 ** floor(log10($max));                      // clean ticks: 1/2/5 × 10^n
    foreach ([1, 2, 5, 10] as $m) { if ($max / ($step * $m) <= 5) { $step *= $m; break; } }
    $top = ceil($max / $step) * $step;
    $n = count($chart); $slot = ($W - $padL - $padR) / max(1, $n);
    $bw = min(24, max(4, $slot - 2));                      // thin bars, 2px air minimum
    $y = fn ($v) => $padT + ($H - $padT - $padB) * (1 - max(0, $v) / $top);
    $every = max(1, (int) ceil($n / 10));                  // ~10 x-labels, never colliding
    $peak = $n ? array_keys(array_column($chart, 'value'), max(array_column($chart, 'value')))[0] : null;
@endphp

<div class="page-head">
    <div><h1>Reports</h1><div class="muted">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</div></div>
    <div class="actions">
        <a class="btn btn-light" href="{{ route('reports.staff') }}">Staff activity</a>
        <a class="btn btn-light" href="{{ route('reports.daily') }}">Night audit (today)</a>
        <a class="btn btn-light" href="{{ route('reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">Download CSV</a>
    </div>
</div>

<form class="filters card" method="GET" style="padding:.9rem 1.1rem">
    <div><label for="from">From</label><input type="date" id="from" name="from" value="{{ $from->toDateString() }}"></div>
    <div><label for="to">To</label><input type="date" id="to" name="to" value="{{ $to->toDateString() }}"></div>
    <div><button class="btn btn-light" type="submit">Show</button></div>
    <div class="actions" style="align-self:end">
        @foreach ([
            'Today' => [today(), today()],
            'This week' => [today()->startOfWeek(), today()],
            'This month' => [today()->startOfMonth(), today()],
            'Last month' => [today()->subMonthNoOverflow()->startOfMonth(), today()->subMonthNoOverflow()->endOfMonth()],
            'This year' => [today()->startOfYear(), today()],
        ] as $label => [$a, $b])
            <a class="btn btn-sm {{ $from->isSameDay($a) && $to->isSameDay($b) ? '' : 'btn-light' }}"
               href="{{ route('reports.index', ['from' => $a->toDateString(), 'to' => $b->toDateString()]) }}">{{ $label }}</a>
        @endforeach
    </div>
</form>

<div class="stats">
    <div class="stat"><div class="stat-label">Total revenue</div><div class="stat-value">{{ $money($t['revenue']) }}</div><div class="stat-note">ETB · rooms {{ $money($t['room']) }} + other {{ $money($t['other']) }}</div></div>
    <div class="stat"><div class="stat-label">Collected</div><div class="stat-value">{{ $money($t['collected']) }}</div><div class="stat-note">ETB received, after refunds</div></div>
    <div class="stat"><div class="stat-label">Occupancy</div><div class="stat-value">{{ $t['occupancy'] }}%</div><div class="stat-note">{{ number_format($t['sold']) }} of {{ number_format($t['available']) }} room nights</div></div>
    <div class="stat"><div class="stat-label">Average rate (ADR)</div><div class="stat-value">{{ $money($t['adr']) }}</div><div class="stat-note">ETB per room night sold</div></div>
    <div class="stat"><div class="stat-label">RevPAR</div><div class="stat-value">{{ $money($t['revpar']) }}</div><div class="stat-note">ETB room revenue per available room</div></div>
    <div class="stat"><div class="stat-label">Owed by in-house guests</div><div class="stat-value">{{ $money($owed) }}</div><div class="stat-note">ETB not yet paid</div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Revenue per {{ $n && count($days) > 62 ? 'month' : 'day' }} <span class="muted small">(ETB)</span></h2></div>
    @if ($t['revenue'] == 0)
        <p class="empty">No revenue in this period.</p>
    @else
    <div class="chart-wrap">
        <svg viewBox="0 0 {{ $W }} {{ $H }}" class="chart" role="img" aria-label="Revenue per day, bar chart. Figures are in the table below.">
            @for ($v = 0; $v <= $top; $v += $step)
                <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y($v) }}" y2="{{ $y($v) }}" class="grid"/>
                <text x="{{ $padL - 8 }}" y="{{ $y($v) + 4 }}" class="axis" text-anchor="end">{{ number_format($v) }}</text>
            @endfor
            @foreach ($chart as $i => $b)
                @php
                    $cx = $padL + $slot * $i + $slot / 2; $yy = $y($b['value']); $h = $y(0) - $yy; $r = min(4, $h, $bw / 2);
                    $x0 = $cx - $bw / 2; $x1 = $cx + $bw / 2; $base = $y(0);
                @endphp
                <g class="bar" data-tip="{{ $b['label'] }}: {{ number_format($b['value'], 2) }} ETB">
                    <rect x="{{ $padL + $slot * $i }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $base - $padT }}" class="hit"/>
                    @if ($h > 0.5)
                        <path d="M{{ $x0 }},{{ $base }} V{{ $yy + $r }} Q{{ $x0 }},{{ $yy }} {{ $x0 + $r }},{{ $yy }} H{{ $x1 - $r }} Q{{ $x1 }},{{ $yy }} {{ $x1 }},{{ $yy + $r }} V{{ $base }} Z" class="col"/>
                    @endif
                    @if ($i % $every === 0)
                        <text x="{{ $cx }}" y="{{ $H - 8 }}" class="axis" text-anchor="middle">{{ $b['short'] }}</text>
                    @endif
                    @if ($i === $peak && $b['value'] > 0)
                        <text x="{{ $cx }}" y="{{ $yy - 6 }}" class="peak" text-anchor="middle">{{ number_format($b['value']) }}</text>
                    @endif
                </g>
            @endforeach
            <line x1="{{ $padL }}" x2="{{ $W - $padR }}" y1="{{ $y(0) }}" y2="{{ $y(0) }}" class="baseline"/>
        </svg>
    </div>
    @endif
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="card-head"><h2>Revenue by department</h2></div>
        <table>
            <tbody>
            @forelse ($byCategory as $label => $amount)
                <tr>
                    <td>{{ $label }}</td>
                    <td style="width:45%"><div class="meter"><span style="width:{{ $t['revenue'] > 0 ? max(0, min(100, $amount / $t['revenue'] * 100)) : 0 }}%"></span></div></td>
                    <td class="right nowrap {{ $amount < 0 ? 'owe' : '' }}">{{ number_format($amount, 2) }}</td>
                </tr>
            @empty
                <tr><td class="empty">Nothing yet.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td>Total</td><td></td><td class="right">{{ number_format($t['revenue'], 2) }}</td></tr></tfoot>
        </table>
    </div>
    <div class="card">
        <div class="card-head"><h2>Payments by method</h2></div>
        <table>
            <tbody>
            @forelse ($byMethod as $method => $amount)
                <tr>
                    <td>{{ \App\Models\Payment::METHODS[$method] ?? $method }}</td>
                    <td style="width:45%"><div class="meter"><span style="width:{{ $t['collected'] > 0 ? max(0, min(100, $amount / $t['collected'] * 100)) : 0 }}%"></span></div></td>
                    <td class="right nowrap">{{ number_format($amount, 2) }}</td>
                </tr>
            @empty
                <tr><td class="empty">No payments in this period.</td></tr>
            @endforelse
            </tbody>
            <tfoot><tr><td>Total</td><td></td><td class="right">{{ number_format($t['collected'], 2) }}</td></tr></tfoot>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Payments by account</h2><span class="small muted">which bank account or wallet the money went into</span></div>
    <table>
        <tbody>
        @forelse ($byAccount as $label => $amount)
            <tr><td>{{ $label }}</td><td class="right nowrap">{{ number_format($amount, 2) }}</td></tr>
        @empty
            <tr><td class="empty">No payments in this period.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><td>Total</td><td class="right">{{ number_format($t['collected'], 2) }}</td></tr></tfoot>
    </table>
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Day by day</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-page-length="31" data-export="daily-report">
        <thead><tr><th>Date</th><th class="right">Rooms sold</th><th class="right">Occupancy</th><th class="right">Room revenue</th><th class="right">Other</th><th class="right">Total</th><th class="right">Collected</th><th></th></tr></thead>
        <tbody>
        @foreach (array_reverse($days) as $d)
            <tr @if ($d['date']->isFuture()) class="muted" @endif>
                <td class="nowrap" data-order="{{ $d['date']->toDateString() }}">{{ $d['date']->format('D d M') }}</td>
                <td class="right" data-order="{{ $d['sold'] }}">{{ $d['sold'] }} / {{ $d['available'] }}</td>
                <td class="right">{{ $d['occupancy'] }}%</td>
                <td class="right">{{ number_format($d['room'], 2) }}</td>
                <td class="right">{{ number_format($d['other'], 2) }}</td>
                <td class="right"><strong>{{ number_format($d['room'] + $d['other'], 2) }}</strong></td>
                <td class="right">{{ number_format($d['collected'], 2) }}</td>
                <td class="right">@unless ($d['date']->isFuture())<a class="small" href="{{ route('reports.daily', ['date' => $d['date']->toDateString()]) }}">Audit</a>@endunless</td>
            </tr>
        @endforeach
        </tbody>
        <tfoot><tr>
            <td>Total</td><td class="right">{{ number_format($t['sold']) }}</td><td class="right">{{ $t['occupancy'] }}%</td>
            <td class="right">{{ number_format($t['room'], 2) }}</td><td class="right">{{ number_format($t['other'], 2) }}</td>
            <td class="right">{{ number_format($t['revenue'], 2) }}</td><td class="right">{{ number_format($t['collected'], 2) }}</td><td></td>
        </tr></tfoot>
    </table>
    </div>
</div>
<p class="small muted">Room revenue is counted for each night a guest stays, at the rate they booked. Food, laundry and other charges are counted on the day they were added to the bill. Occupancy uses the rooms currently in service.</p>
@endsection
