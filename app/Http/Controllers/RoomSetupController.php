<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Setup → Rooms: add, edit and remove single rooms.
 */
class RoomSetupController extends Controller
{
    public function create()
    {
        return view('setup.room-form', ['room' => new Room(['floor' => 1]), 'types' => RoomType::orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $room = Room::create($this->validated($request));

        return redirect()->route('setup.rooms')->with('success', "Room {$room->number} added.");
    }

    public function edit(Room $room)
    {
        return view('setup.room-form', ['room' => $room, 'types' => RoomType::orderBy('name')->get()]);
    }

    public function update(Request $request, Room $room)
    {
        $room->update($this->validated($request, $room));

        return redirect()->route('setup.rooms')->with('success', "Room {$room->number} updated.");
    }

    public function destroy(Room $room)
    {
        if ($room->reservations()->withTrashed()->exists()) {
            return back()->with('error', "Room {$room->number} has booking history, so it can't be deleted. Mark it out of order instead.");
        }
        $room->delete();

        return back()->with('success', 'Room deleted.');
    }

    private function validated(Request $request, ?Room $room = null): array
    {
        $data = $request->validate([
            'number' => ['required', 'string', 'max:10', Rule::unique('rooms', 'number')->ignore($room?->id)],
            'room_type_id' => ['required', 'exists:room_types,id'],
            'floor' => ['required', 'integer', 'min:0', 'max:50'],
            'rate_override' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $data['rate_override'] = $data['rate_override'] ?? null;

        return $data;
    }
}
