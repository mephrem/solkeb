<?php

namespace App\Http\Controllers;

use App\Models\Guest;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** Printable list for the kitchen: who stayed the night before and has breakfast this morning. */
class BreakfastController extends Controller
{
    public function __invoke(Request $request)
    {
        $date = Carbon::parse($request->query('date', today()->toDateString()))->startOfDay();
        if ($date->gt(today()->addDay())) {
            $date = today()->addDay(); // the list for tomorrow can be printed the evening before
        }
        $night = $date->copy()->subDay();

        // Slept here the night before: in house (also anyone overdue), or checked out that morning
        $stays = Reservation::with(['guest', 'organization', 'rooms'])
            ->whereNotNull('checked_in_at')
            ->whereIn('status', ['checked_in', 'checked_out'])
            ->whereDate('check_in_date', '<=', $night)
            ->where(fn ($q) => $q->whereDate('check_out_date', '>=', $date)->orWhere('status', 'checked_in'))
            ->get();

        // Printing tomorrow's list tonight: include guests still expected to arrive today
        $expected = $night->isToday()
            ? Reservation::with(['guest', 'organization', 'rooms'])->arrivingOn($night)->get()
            : collect();

        $occupants = Guest::whereIn('id', $stays->flatMap(fn ($r) => $r->rooms->pluck('pivot.guest_id'))->filter()->unique())
            ->get()->keyBy('id');

        // One line per room, each with the guest staying in it (no grouping, even for one organisation)
        $rows = $stays->map(fn ($r) => [$r, false])->merge($expected->map(fn ($r) => [$r, true]))
            ->flatMap(function ($item) use ($occupants) {
                [$r, $isExpected] = $item;
                $rooms = $r->rooms->sortBy('number', SORT_NATURAL)->values();
                $n = $rooms->count();

                // The booking's adults and children shared out over its rooms (5 adults in 3 rooms: 2, 2, 1)
                return $rooms->map(fn ($room, $i) => (object) [
                    'r' => $r,
                    'room' => $room->number,
                    'name' => ($occupants[$room->pivot->guest_id] ?? $r->guest)->full_name,
                    'adults' => intdiv($r->adults, $n) + ($i < $r->adults % $n ? 1 : 0),
                    'children' => intdiv($r->children, $n) + ($i < $r->children % $n ? 1 : 0),
                    'expected' => $isExpected,
                ]);
            })
            ->sortBy('room', SORT_NATURAL)->values();

        return view('breakfast', [
            'date' => $date,
            'rows' => $rows,
            'adults' => $rows->sum('adults'),
            'children' => $rows->sum('children'),
        ]);
    }
}
