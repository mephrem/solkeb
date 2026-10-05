@extends('layouts.app')
@section('title', 'Guests')
@section('content')
<div class="page-head">
    <h1>Guests</h1>
    @if (auth()->user()->hasRole('admin','manager','receptionist'))
        <a class="btn" href="{{ route('guests.create') }}">+ Add guest</a>
    @endif
</div>
<div class="card" style="padding:0">
    <div class="table-wrap">
    <table class="datatable" data-export="guests">
        <thead><tr><th>Name</th><th>Phone</th><th>ID</th><th>Nationality</th><th class="right">Stays</th><th></th></tr></thead>
        <tbody>
        @forelse ($guests as $g)
            <tr>
                <td><a href="{{ route('guests.show', $g) }}"><strong>{{ $g->full_name }}</strong></a>
                    @if ($g->is_vip) <span class="badge b-vip">VIP</span> @endif
                    @if ($g->is_blacklisted) <span class="badge b-cancelled">Blacklisted</span> @endif</td>
                <td>{{ $g->phone }}</td>
                <td class="small">{{ $g->id_number ?: '—' }}@if ($g->tin)<div class="muted">TIN {{ $g->tin }}</div>@endif</td>
                <td>{{ $g->nationality }}</td>
                <td class="right">{{ $g->reservations_count }}</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('guests.show', $g) }}">View</a>
                    @if (auth()->user()->hasRole('admin','manager','receptionist'))
                        <a class="btn btn-sm btn-light" href="{{ route('guests.edit', $g) }}">Edit</a>
                        @include('partials.delete', ['action' => route('guests.destroy', $g), 'name' => $g->full_name,
                            'text' => 'The guest record will be removed.',
                            'block' => $g->history_count ? "{$g->full_name} has booking history, which must be kept." : null])
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No guests found.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
