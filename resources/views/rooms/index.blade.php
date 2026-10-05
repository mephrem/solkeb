@extends('layouts.app')
@section('title', 'Room board')
@section('content')
@php
    $u = auth()->user();
    $canEdit = $u->hasRole('admin', 'manager', 'receptionist');
    $label = ['available' => 'Available', 'reserved' => 'Reserved', 'occupied' => 'Occupied', 'out_of_order' => 'Out of order'];
@endphp
<div class="page-head">
    <div>
        <h1>Room board</h1>
        <div class="muted">
            {{ $counts['occupied'] ?? 0 }} occupied · {{ $counts['reserved'] ?? 0 }} reserved · {{ $counts['available'] ?? 0 }} available
            · {{ $counts['dirty'] }} need cleaning · {{ $counts['out_of_order'] ?? 0 }} out of order
            @if ($counts['due']) · <strong class="owe">{{ $counts['due'] }} due out today or overdue</strong>@endif
        </div>
    </div>
    <div class="legend">
        <span class="lg lg-available">Available</span>
        <span class="lg lg-reserved">Reserved (arriving)</span>
        <span class="lg lg-occupied">Occupied</span>
        <span class="lg lg-due">Leaves today / overdue</span>
        <span class="lg lg-ooo">Out of order</span>
    </div>
</div>

@if ($groups->isNotEmpty())
    <div class="card groups-key">
        <strong>Group bookings</strong> <span class="muted small">rooms with the same colour belong to one booking</span>
        <div class="actions" style="margin-top:.5rem">
            @foreach ($groups as $g)
                <a class="group-chip" style="--g: {{ $g->group_color }}" href="{{ route('reservations.show', $g) }}">
                    {{ $g->groupName() }} · {{ $g->rooms_count }} rooms · {{ $g->status === 'checked_in' ? 'in house' : 'arriving' }}
                </a>
            @endforeach
        </div>
    </div>
@endif

@foreach ($floors as $floor => $items)
<div class="floor">
    <h2>Floor {{ $floor }}</h2>
    <div class="rooms">
        @foreach ($items as $b)
            @php $room = $b->room; @endphp
            <div class="rm rm-{{ $b->state }} {{ $b->due ? 'rm-due' : '' }} {{ $b->color ? 'rm-group' : '' }}" @if ($b->color) style="--g: {{ $b->color }}" @endif>
                <div class="rm-top">
                    <span class="rm-num">{{ $room->number }}</span>
                    <span class="rm-state">{{ $b->due === 'today' ? 'Leaves today' : ($b->due === 'overdue' ? 'Overdue' : $label[$b->state]) }}</span>
                </div>
                <div class="rm-type">{{ $room->roomType->name }} · {{ number_format($room->rate()) }} ETB</div>

                @if ($b->color)
                    <div class="rm-chip" title="Group booking {{ $b->booking->code }}">{{ $b->booking->groupName() }}</div>
                @endif

                @if ($b->booking)
                    <div class="rm-guest">
                        @if ($u->hasRole('admin', 'manager', 'receptionist', 'accountant'))
                            <a href="{{ route('reservations.show', $b->booking) }}">{{ $b->guest->full_name }}</a>
                        @else
                            {{ $b->guest->first_name }}
                        @endif
                    </div>
                    <div class="rm-dates">
                        @if ($b->state === 'reserved')
                            {{ $b->booking->check_in_date->isToday() ? 'Arrives today' : 'Was due '.$b->booking->check_in_date->format('d M') }}
                            · {{ $b->booking->nights() }} night{{ $b->booking->nights() == 1 ? '' : 's' }}
                        @elseif ($b->due)
                            {{ $b->booking->departureLabel() }}{{ $b->due === 'overdue' ? ' · was due '.$b->booking->check_out_date->format('d M') : '' }}
                        @else
                            until {{ $b->booking->check_out_date->format('d M') }}
                        @endif
                    </div>
                @endif

                @if ($b->next)
                    <div class="rm-next {{ $b->next->check_in_date->lte(today()) ? 'rm-next-today' : '' }}">
                        @if ($b->next->group_color)<i style="background: {{ $b->next->group_color }}"></i>@endif
                        Next: {{ $b->next->check_in_date->lte(today()) ? 'today' : $b->next->check_in_date->format('d M') }} · {{ \Illuminate\Support\Str::limit($b->next->groupName(), 18) }}
                    </div>
                @endif

                <div class="rm-foot">
                    @if ($b->dirty)
                        <span class="rm-dirty">Needs cleaning</span>
                        @if ($canEdit)
                            <form method="POST" action="{{ route('rooms.update', $room) }}" class="inline">
                                @csrf @method('PATCH')
                                <input type="hidden" name="housekeeping_status" value="clean">
                                <button class="rm-btn" type="submit">Mark clean</button>
                            </form>
                        @endif
                    @endif
                    @if ($canEdit && $b->state !== 'occupied' && $b->state !== 'reserved')
                        <form method="POST" action="{{ route('rooms.update', $room) }}" class="inline"
                              @if ($b->state !== 'out_of_order') data-confirm-title="Take room {{ $room->number }} out of order?" data-confirm="It won't be offered for new bookings until you put it back in service." data-confirm-button="Out of order" data-confirm-danger @endif>
                            @csrf @method('PATCH')
                            <input type="hidden" name="out_of_order" value="{{ $b->state === 'out_of_order' ? 0 : 1 }}">
                            <button class="ooo-btn" type="submit">{{ $b->state === 'out_of_order' ? 'Back in service' : 'Out of order' }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endforeach
@endsection
