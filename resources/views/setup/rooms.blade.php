@extends('layouts.app')
@section('title', 'Rooms & rates')
@section('content')
<div class="page-head">
    <div><h1>Rooms &amp; rates</h1><div class="muted">{{ $rooms->count() }} rooms in {{ $types->count() }} types</div></div>
    <div class="actions">
        <a class="btn btn-light" href="{{ route('room-types.create') }}">+ Room type</a>
        <a class="btn btn-light" href="{{ route('room-import.create') }}">Import rooms</a>
        <a class="btn" href="{{ route('room-setup.create') }}">+ Room</a>
    </div>
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Room types &amp; prices</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-page-length="10" data-export="room-types">
        <thead><tr><th>Type</th><th class="right">Rate / night (ETB)</th><th>Guests</th><th>Amenities</th><th class="right">Rooms</th><th></th></tr></thead>
        <tbody>
        @forelse ($types as $t)
            <tr>
                <td><strong>{{ $t->name }}</strong> @unless ($t->is_active)<span class="badge">Hidden</span>@endunless
                    @if ($t->description)<div class="small muted">{{ $t->description }}</div>@endif</td>
                <td class="right"><strong>{{ number_format($t->base_rate, 2) }}</strong></td>
                <td class="small">{{ $t->max_adults }} {{ \Illuminate\Support\Str::plural('adult', $t->max_adults) }}{{ $t->max_children ? ', '.$t->max_children.' '.\Illuminate\Support\Str::plural('child', $t->max_children) : '' }}</td>
                <td class="small">{{ implode(', ', $t->amenities ?? []) }}</td>
                <td class="right">{{ $t->rooms_count }}</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('room-types.edit', $t) }}">Edit</a>
                    @include('partials.delete', ['action' => route('room-types.destroy', $t), 'name' => $t->name,
                        'text' => 'The room type will be removed.',
                        'block' => $t->rooms_count ? "{$t->name} still has {$t->rooms_count} room(s). Move them to another type or delete them first." : null])
                </td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty">No room types yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>

<div class="card" style="padding:0">
    <div class="card-head" style="padding:1rem 1.25rem 0"><h2>Rooms</h2></div>
    <div class="table-wrap">
    <table class="datatable" data-export="rooms">
        <thead><tr><th>Room</th><th>Floor</th><th>Type</th><th class="right">Rate (ETB)</th><th>Status</th><th>Notes</th><th></th></tr></thead>
        <tbody>
        @forelse ($rooms as $room)
            <tr>
                <td><strong>{{ $room->number }}</strong></td>
                <td>{{ $room->floor }}</td>
                <td>{{ $room->roomType->name }}</td>
                <td class="right">{{ number_format($room->rate(), 2) }}
                    @if ($room->rate_override !== null)<div class="small muted">own price</div>@endif</td>
                <td>@include('partials.status', ['s' => $room->status])</td>
                <td class="small">{{ $room->notes }}</td>
                <td class="right nowrap">
                    <a class="btn btn-sm btn-light" href="{{ route('room-setup.edit', $room) }}">Edit</a>
                    @include('partials.delete', ['action' => route('room-setup.destroy', $room), 'name' => 'room '.$room->number,
                        'text' => 'The room will be removed from the hotel.',
                        'block' => $room->reservations_count ? "Room {$room->number} has booking history, which must be kept. Use \"Mark out of order\" on the room board to stop selling it." : null])
                </td>
            </tr>
        @empty
            <tr><td colspan="7" class="empty">No rooms yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    </div>
</div>
@endsection
