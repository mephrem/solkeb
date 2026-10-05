@extends('layouts.app')
@section('title', 'Breakfast list ' . $date->format('d M Y'))
@section('content')
    <div class="page-head">
        <div>
            <h1>Breakfast list</h1>
            <div class="muted">{{ $date->format('l, d F Y') }} · guests who stayed the night of
                {{ $date->copy()->subDay()->format('d M') }} · printed by {{ auth()->user()->name }} at
                {{ now()->format('H:i') }}
            </div>
        </div>
        <div class="actions no-print">
            <a class="btn btn-light" href="{{ route('breakfast', ['date' => $date->copy()->subDay()->toDateString()]) }}">‹
                Previous day</a>
            @if ($date->lt(today()->addDay()))<a class="btn btn-light"
            href="{{ route('breakfast', ['date' => $date->copy()->addDay()->toDateString()]) }}">{{ $date->isToday() ? 'Tomorrow ›' : 'Next day ›' }}</a>@endif
            <button class="btn" type="button" onclick="window.print()">Print</button>
        </div>
    </div>

    <div class="card" style="padding:0">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Room</th>
                        <th>Guest</th>
                        <th>Organisation</th>
                        <th class="right">Adults</th>
                        <th class="right">Children</th>
                        <th style="width:90px">Served</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $x)
                        <tr>
                            <td class="nowrap"><strong>{{ $x->room }}</strong></td>
                            <td>{{ $x->name }}@if ($x->expected) <span class="badge">expected tonight</span>@endif
                            </td>
                            <td class="small">{{ $x->r->organization?->name ?? $x->r->guest->company }}</td>
                            <td class="right">{{ $x->adults }}</td>
                            <td class="right">{{ $x->children ?: '' }}</td>
                            <td><span
                                    style="display:inline-block;width:18px;height:18px;border:1.5px solid #94a3b8;border-radius:3px"></span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="empty">No guests stayed the night before.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <td colspan="3">Total</td>
                            <td class="right">{{ $adults }}</td>
                            <td class="right">{{ $children }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection