<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Activity;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Checking guests in: every room gets the guest staying in it, with their ID details and ID photos.
 *
 * Rooms whose guest has not come yet can be left out: they move to a booking of their own
 * (same dates, organisation and billing) that is checked in later or marked no-show.
 */
class CheckInService
{
    /**
     * Guard rails before the form is even validated.
     *
     * @throws BookingException
     */
    public function ensureCanCheckIn(Reservation $reservation): void
    {
        if (! $reservation->isUpcoming()) {
            throw new BookingException('This booking cannot be checked in.');
        }
        if ($reservation->check_in_date->isAfter(today())) {
            throw new BookingException('Check-in date is '.$reservation->check_in_date->format('d M Y').'. Change the dates to check in early.');
        }
    }

    /**
     * @param  Collection<int, Room>  $arriving  rooms whose guest is here now
     * @param  Collection<int, int>  $absent  ids of rooms whose guest has not come
     * @param  array<int, array>  $occupants  validated details per room id
     * @param  array<int, array{front: ?UploadedFile, back: ?UploadedFile}>  $photos  ID photos per room id
     * @return Reservation|null the new booking holding the rooms that have not arrived, if any
     *
     * @throws ValidationException when an ID photo is missing
     * @throws BookingException when a room still has the previous guest in it
     */
    public function checkIn(Reservation $reservation, Collection $arriving, Collection $absent, array $occupants, array $photos, User $by): ?Reservation
    {
        $plan = $this->plan($arriving, $occupants, $photos);

        $busy = $arriving->filter(fn ($room) => $room->status === 'occupied');
        if ($busy->isNotEmpty()) {
            throw new BookingException('Room '.$busy->pluck('number')->join(', ').' is still occupied. Check the previous guest out first.');
        }

        return DB::transaction(function () use ($reservation, $plan, $absent, $by) {
            $split = $absent->isNotEmpty() ? $this->splitOff($reservation, $absent) : null;

            $names = $this->saveOccupants($reservation, $plan);

            // A guest arriving after the planned date is billed from today
            if ($reservation->check_in_date->isBefore(today())) {
                $reservation->check_in_date = today();
                if ($reservation->check_out_date->lte(today())) {
                    $reservation->check_out_date = today()->addDay();
                }
            }

            $reservation->forceFill([
                'status' => Reservation::CHECKED_IN,
                'checked_in_at' => now(),
                'checked_in_by' => $by->id,
            ])->save();

            $this->addRoomCharges($reservation, $by);
            Activity::log($reservation, 'checked_in', implode('; ', $names));

            return $split;
        });
    }

    /**
     * Match each room's guest with an existing record (same ID, or same phone and first name)
     * so returning guests are not duplicated, and check every guest has their ID photos.
     *
     * @return array<int, array{0: Room, 1: ?Guest, 2: array, 3: ?UploadedFile, 4: ?UploadedFile}>
     *
     * @throws ValidationException
     */
    private function plan(Collection $arriving, array $occupants, array $photos): array
    {
        $plan = [];
        $missing = [];

        foreach ($arriving as $room) {
            $o = $occupants[$room->id];
            $guest = Guest::where('id_type', $o['id_type'])->where('id_number', trim($o['id_number']))->first()
                ?? Guest::where('phone', trim($o['phone']))->get()
                    ->first(fn ($g) => mb_strtolower(trim($g->first_name)) === mb_strtolower(trim($o['first_name'])));
            $front = $photos[$room->id]['front'] ?? null;
            $back = $photos[$room->id]['back'] ?? null;

            // A returning guest whose ID photos are already saved doesn't need new ones; a passport has no back
            if (! $front && ! $guest?->id_front_path) {
                $missing["occupants.{$room->id}.id_front"] = "Room {$room->number}: attach a photo of the front of the ID.";
            }
            if (! $back && ! $guest?->id_back_path && $o['id_type'] !== 'passport') {
                $missing["occupants.{$room->id}.id_back"] = "Room {$room->number}: attach a photo of the back of the ID.";
            }

            $plan[$room->id] = [$room, $guest, $o, $front, $back];
        }

        if ($missing) {
            throw ValidationException::withMessages($missing);
        }

        return $plan;
    }

    /**
     * Save each room's guest (new or updated), store their ID photos privately and link them to the room.
     *
     * @return string[] "Room 201: Name" lines for the history
     */
    private function saveOccupants(Reservation $reservation, array $plan): array
    {
        $names = [];
        $saved = [];

        foreach ($plan as $roomId => [$room, $guest, $o, $front, $back]) {
            // The same person may take two rooms: reuse the record already saved in this check-in
            $key = $o['id_type'].'|'.trim($o['id_number']);
            $guest = $saved[$key] ?? $guest ?? new Guest(['nationality' => 'Ethiopia']);
            $guest->fill([
                'first_name' => trim($o['first_name']),
                'father_name' => $o['father_name'] ?? $guest->father_name,
                'phone' => trim($o['phone']),
                'id_type' => $o['id_type'],
                'id_number' => trim($o['id_number']),
            ])->save();

            foreach (['id_front_path' => $front, 'id_back_path' => $back] as $column => $file) {
                if ($file) {
                    $old = $guest->{$column};
                    $guest->{$column} = $file->store("ids/{$guest->id}", 'local'); // private disk, not the public web folder
                    if ($old) {
                        Storage::disk('local')->delete($old);
                    }
                }
            }
            $guest->save();

            $saved[$key] = $guest;
            $reservation->rooms()->updateExistingPivot($roomId, ['guest_id' => $guest->id]);
            $names[] = "Room {$room->number}: {$guest->full_name}";
        }

        return $names;
    }

    /** Mark the rooms occupied and put the room nights on the bill, at the price fixed when booking. */
    private function addRoomCharges(Reservation $reservation, User $by): void
    {
        $nights = $reservation->nights();

        foreach ($reservation->rooms()->with('roomType')->get() as $room) {
            $room->update(['status' => 'occupied']);
            $reservation->charges()->create([
                'room_id' => $room->id,
                'type' => 'room',
                'description' => "Room {$room->number} ({$room->roomType->name})",
                'quantity' => $nights,
                'unit_price' => $room->pivot->nightly_rate,
                'amount' => round($nights * $room->pivot->nightly_rate, 2),
                'charge_date' => today(),
                'created_by' => $by->id,
            ]);
        }
    }

    /**
     * Move the rooms whose guests have not arrived to a new booking with the same dates, organisation
     * and billing, so the rest of the group can be checked in now.
     *
     * @param  Collection<int, int>  $roomIds
     */
    private function splitOff(Reservation $reservation, Collection $roomIds): Reservation
    {
        $rooms = $reservation->rooms->whereIn('id', $roomIds->all());
        $adults = max(1, min($reservation->adults - 1, $rooms->count())); // one adult per room that has not come

        $new = Reservation::create([
            'guest_id' => $reservation->guest_id,
            'organization_id' => $reservation->organization_id,
            'on_credit' => $reservation->on_credit,
            'check_in_date' => $reservation->check_in_date,
            'check_out_date' => $reservation->check_out_date,
            'adults' => $adults,
            'children' => 0,
            'source' => $reservation->source,
            'special_requests' => $reservation->special_requests,
            'status' => Reservation::CONFIRMED,
            'created_by' => $reservation->created_by,
        ]);
        foreach ($rooms as $room) {
            $new->rooms()->attach($room->id, ['nightly_rate' => $room->pivot->nightly_rate]);
        }
        $reservation->rooms()->detach($rooms->pluck('id')->all());
        $reservation->update(['adults' => max(1, $reservation->adults - $adults)]);
        $reservation->assignGroupColor();
        $new->assignGroupColor();

        $numbers = $rooms->pluck('number')->join(', ');
        Activity::log($reservation, 'edited', "Room {$numbers} not arrived: moved to booking {$new->code}");
        Activity::log($new, 'reserved', "Room {$numbers}, split from {$reservation->code} (not arrived at check-in)");

        return $new->load('rooms');
    }
}
