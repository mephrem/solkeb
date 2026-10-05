<?php

namespace App\Http\Controllers;

use App\Http\Requests\GuestRequest;
use App\Models\Guest;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/** Guest records, their stay history and their ID photos (stored privately). */
class GuestController extends Controller
{
    public function index(Request $request)
    {
        $guests = Guest::withCount(['reservations', 'reservations as history_count' => fn ($q) => $q->withTrashed()])->latest()->get();

        return view('guests.index', compact('guests'));
    }

    public function show(Guest $guest)
    {
        // Their own bookings, plus stays in a room of someone else's booking (organisation bookings)
        $stays = Reservation::with('rooms')
            ->where(fn ($q) => $q->where('guest_id', $guest->id)
                ->orWhereHas('rooms', fn ($r) => $r->where('reservation_room.guest_id', $guest->id)))
            ->latest('check_in_date')->get();
        $guest->setRelation('reservations', $stays);

        return view('guests.show', compact('guest'));
    }

    public function create()
    {
        return view('guests.form', ['guest' => new Guest(['nationality' => 'Ethiopia'])]);
    }

    public function store(GuestRequest $request)
    {
        $guest = Guest::create($request->guestData());
        $this->saveIdPhotos($request, $guest);

        return redirect()->route('guests.show', $guest)->with('success', 'Guest saved.');
    }

    public function edit(Guest $guest)
    {
        return view('guests.form', compact('guest'));
    }

    public function update(GuestRequest $request, Guest $guest)
    {
        $guest->update($request->guestData());
        $this->saveIdPhotos($request, $guest);

        return redirect()->route('guests.show', $guest)->with('success', 'Guest updated.');
    }

    public function destroy(Guest $guest)
    {
        if ($guest->hasHistory()) {
            return back()->with('error', "{$guest->full_name} has bookings on record, so the guest can't be deleted.");
        }

        Storage::disk('local')->delete(array_filter([$guest->id_front_path, $guest->id_back_path]));
        $guest->delete();

        return redirect()->route('guests.index')->with('success', "{$guest->full_name} deleted.");
    }

    /** Show a guest's ID photo to logged-in front-desk staff only (the files are not in the public folder). */
    public function idPhoto(Guest $guest, string $side)
    {
        $path = $side === 'back' ? $guest->id_back_path : $guest->id_front_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }

    private function saveIdPhotos(Request $request, Guest $guest): void
    {
        foreach (['id_front' => 'id_front_path', 'id_back' => 'id_back_path'] as $field => $column) {
            if ($request->hasFile($field)) {
                $old = $guest->{$column};
                $guest->{$column} = $request->file($field)->store("ids/{$guest->id}", 'local');
                if ($old) {
                    Storage::disk('local')->delete($old);
                }
            }
        }
        $guest->save();
    }
}
