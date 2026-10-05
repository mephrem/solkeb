<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The room board (every room by floor and its state right now) and quick room updates: mark clean, out of order.
 */
class RoomController extends Controller
{
    /** Room board: every room by floor with its current state. */
    public function index()
    {
        $today = today();
        $rooms = Room::with(['roomType', 'reservations' => function ($q) use ($today) {
            $q->where(fn ($w) => $w->where('status', 'checked_in')
                ->orWhere(fn ($r) => $r->whereIn('status', ['pending', 'confirmed'])->whereDate('check_out_date', '>', $today)))
                ->with('guest')->withCount('rooms')->orderBy('check_in_date');
        }])->orderBy('floor')->orderBy('number')->get();

        $occupants = Guest::whereIn('id', $rooms->flatMap(fn ($r) => $r->reservations->pluck('pivot.guest_id'))->filter()->unique())
            ->get()->keyBy('id');

        // What each room is right now: occupied, reserved (guest due today or late), available or out of order
        $board = $rooms->map(function (Room $room) use ($today, $occupants) {
            $stay = $room->reservations->firstWhere('status', 'checked_in');
            $held = $room->reservations->first(fn ($r) => $r->status !== 'checked_in' && $r->check_in_date->lte($today));
            // A guest leaving today may already have the next one arriving today: show that as "Next: today"
            $next = $stay && $held ? $held
                : $room->reservations->first(fn ($r) => $r->status !== 'checked_in' && $r->check_in_date->gt($today));
            $booking = $stay ?? $held;
            $state = $room->status === 'out_of_order' ? 'out_of_order' : ($stay ? 'occupied' : ($held ? 'reserved' : 'available'));

            return (object) [
                'room' => $room,
                'state' => $state,
                'booking' => $booking,
                'guest' => $booking ? ($occupants[$booking->pivot->guest_id] ?? $booking->guest) : null,
                'color' => $booking?->group_color,
                'next' => $next,
                'dirty' => $room->housekeeping_status === 'dirty',
                'due' => $stay?->departureState(), // 'today' or 'overdue': shown in red
            ];
        });

        $groups = $board->filter(fn ($b) => $b->color)->map(fn ($b) => $b->booking)->unique('id')->values();

        return view('rooms.index', [
            'floors' => $board->groupBy(fn ($b) => $b->room->floor),
            'counts' => $board->countBy('state')->all() + ['dirty' => $board->where('dirty', true)->count(),
                'due' => $board->whereNotNull('due')->count()],
            'groups' => $groups,
        ]);
    }

    public function update(Request $request, Room $room)
    {
        $data = $request->validate([
            'housekeeping_status' => ['nullable', Rule::in(['clean', 'dirty'])],
            'out_of_order' => ['nullable', 'boolean'],
        ]);

        if (isset($data['housekeeping_status'])) {
            $room->housekeeping_status = $data['housekeeping_status'];
        }

        if ($request->has('out_of_order')) {
            if ($room->status === 'occupied') {
                return back()->with('error', "Room {$room->number} is occupied and cannot be put out of order.");
            }
            $room->status = $request->boolean('out_of_order') ? 'out_of_order' : 'available';
        }

        $room->save();

        return back()->with('success', isset($data['housekeeping_status']) && $data['housekeeping_status'] === 'clean'
            ? "Room {$room->number} marked clean." : "Room {$room->number} updated.");
    }
}
