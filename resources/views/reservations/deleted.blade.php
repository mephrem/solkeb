@extends('layouts.app')
@section('title', 'Deleted bookings')
@section('content')
<div class="page-head">
    <div><h1>Deleted bookings</h1><div class="muted">Bookings removed by a manager, with who removed them and why. They are left out of all reports.</div></div>
    <a class="btn btn-light" href="{{ route('reservations.index') }}">Back to reservations</a>
</div>
<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="deleted-bookings" data-order="0,desc">
        <thead><tr><th>Deleted</th><th>Booking</th><th>Guest</th><th>Stay</th><th>Status then</th><th class="right">Charged</th><th class="right">Paid</th><th>Deleted by</th><th>Reason</th><th></th></tr></thead>
        <tbody>
        @forelse ($list as $r)
            <tr>
                <td class="nowrap" data-order="{{ $r->deleted_at->format('Y-m-d H:i:s') }}">{{ $r->deleted_at->format('d M Y H:i') }}</td>
                <td class="nowrap"><strong>{{ $r->code }}</strong><div class="small muted">by {{ $r->creator?->name ?? '—' }}</div></td>
                <td>{{ $r->guest->full_name }}<div class="small muted">{{ $r->guest->phone }}</div></td>
                <td class="nowrap small" data-order="{{ $r->check_in_date->toDateString() }}">Room {{ $r->rooms->pluck('number')->join(', ') }}<br>{{ $r->check_in_date->format('d M') }} → {{ $r->check_out_date->format('d M Y') }}</td>
                <td>@include('partials.status', ['s' => $r->status])</td>
                <td class="right">{{ number_format($charges[$r->id] ?? 0, 2) }}</td>
                <td class="right {{ ($paid[$r->id] ?? 0) > 0 ? 'owe' : '' }}">{{ number_format($paid[$r->id] ?? 0, 2) }}</td>
                <td>{{ $r->deleter?->name ?? '—' }}</td>
                <td class="small">{{ $r->delete_reason }}</td>
                <td class="right">
                    @if (auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('reservations.restore', $r->id) }}" class="inline"
                              data-confirm-title="Restore {{ $r->code }}?" data-confirm="The booking and its payments come back into the lists and reports." data-confirm-button="Restore">
                            @csrf <button class="btn btn-sm btn-light" type="submit">Restore</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="empty">No bookings have been deleted.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
<p class="small muted">Payments on a deleted booking are left out of reports and the cash-up. If money was really received, record it on the correct booking.</p>
@endsection
