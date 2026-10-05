<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** "Rooms & rates": room types (with their price) and the rooms in each. */
class RoomTypeController extends Controller
{
    public function index()
    {
        return view('setup.rooms', [
            'types' => RoomType::withCount('rooms')->orderBy('base_rate')->get(),
            'rooms' => Room::with('roomType')->withCount(['reservations' => fn ($q) => $q->withTrashed()])->orderBy('floor')->orderBy('number')->get(),
        ]);
    }

    public function create()
    {
        return view('setup.room-type-form', ['type' => new RoomType(['max_adults' => 2, 'max_children' => 0, 'is_active' => true])]);
    }

    public function store(Request $request)
    {
        RoomType::create($this->validated($request));

        return redirect()->route('setup.rooms')->with('success', 'Room type added.');
    }

    public function edit(RoomType $roomType)
    {
        return view('setup.room-type-form', ['type' => $roomType]);
    }

    public function update(Request $request, RoomType $roomType)
    {
        $roomType->update($this->validated($request, $roomType));

        return redirect()->route('setup.rooms')->with('success', "{$roomType->name} updated. New bookings use the new rate; existing bookings keep theirs.");
    }

    public function destroy(RoomType $roomType)
    {
        if ($roomType->rooms()->exists()) {
            return back()->with('error', "Move or delete the rooms in {$roomType->name} first.");
        }
        $roomType->delete();

        return back()->with('success', 'Room type deleted.');
    }

    private function validated(Request $request, ?RoomType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('room_types', 'name')->ignore($type?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'base_rate' => ['required', 'numeric', 'min:0'],
            'max_adults' => ['required', 'integer', 'min:1', 'max:20'],
            'max_children' => ['required', 'integer', 'min:0', 'max:20'],
            'amenities' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['amenities'] = array_values(array_filter(array_map('trim', explode(',', (string) ($data['amenities'] ?? '')))));
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
