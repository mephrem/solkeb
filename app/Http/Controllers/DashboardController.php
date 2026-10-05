<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;

/**
 * Today at a glance: occupancy, arrivals, departures (overdue first), rooms to clean and money collected.
 */
class DashboardController extends Controller
{
    public function __invoke()
    {
        $today = today();

        $sellable = Room::where('status', '!=', 'out_of_order')->count();
        $occupied = Room::where('status', 'occupied')->count();

        return view('dashboard', [
            'arrivals' => Reservation::with(['guest', 'rooms'])->arrivingOn($today)->orderBy('id')->get(),
            // Guests leaving today, with anyone who should already have left listed first
            'departures' => Reservation::with(['guest', 'rooms'])->where('status', 'checked_in')
                ->whereDate('check_out_date', '<=', $today)->orderBy('check_out_date')->orderBy('id')->get(),
            'overdue' => Reservation::with(['guest', 'rooms'])->where('status', 'checked_in')
                ->whereDate('check_out_date', '<', $today)->get(),
            'inHouse' => Reservation::where('status', 'checked_in')->count(),
            'occupied' => $occupied,
            'sellable' => $sellable,
            'occupancy' => $sellable ? round($occupied / $sellable * 100) : 0,
            'dirtyRooms' => Room::where('housekeeping_status', 'dirty')->count(),
            'takenToday' => (float) Payment::whereDate('paid_at', $today)->whereIn('type', ['deposit', 'payment'])->sum('amount')
                - (float) Payment::whereDate('paid_at', $today)->where('type', 'refund')->sum('amount'),
        ]);
    }
}
