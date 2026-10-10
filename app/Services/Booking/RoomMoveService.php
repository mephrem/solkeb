<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Activity;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moving a guest who is in house to another room (room shifting).
 *
 * The bill is split: the nights already spent stay on the old room's line, the rest of the stay goes on
 * a new line for the new room, at the new room's price unless staff keep the old price. The old room is
 * freed and marked "Needs cleaning"; the guest staying in it moves with the booking.
 */
class RoomMoveService
{
    /**
     * Rooms a guest could move into for the rest of their stay: free from today to the check-out date.
     *
     * @return Collection<int, Room>
     */
    public function freeRooms(Reservation $reservation): Collection
    {
        if ($reservation->status !== Reservation::CHECKED_IN || ! $reservation->check_out_date->isAfter(today())) {
            return collect();
        }

        return Room::with('roomType')
            ->availableBetween(today()->toDateString(), $reservation->check_out_date->toDateString())
            ->where('status', '!=', 'occupied')
            ->whereHas('roomType', fn ($q) => $q->where('is_active', true))
            ->orderBy('floor')->orderBy('number')
            ->get();
    }

    /**
     * @param  bool  $keepPrice  charge the remaining nights at the old room's price
     *
     * @throws BookingException when the guest is not in house, the stay has ended, or the new room is not free
     */
    public function move(Reservation $reservation, Room $from, Room $to, bool $keepPrice, string $reason, User $by): void
    {
        if ($reservation->status !== Reservation::CHECKED_IN) {
            throw new BookingException('Only a guest who is checked in can move room. To change the room before arrival, use Edit booking.');
        }
        if (! $reservation->check_out_date->isAfter(today())) {
            throw new BookingException('The stay ends today or has already ended. Extend the stay first, then move the room.');
        }

        $old = $reservation->rooms()->with('roomType')->where('rooms.id', $from->id)->first()
            ?? throw new BookingException("Room {$from->number} is not on this booking.");
        if (! $this->freeRooms($reservation)->contains('id', $to->id)) {
            throw new BookingException("Room {$to->number} is not free for the rest of this stay. Choose another room.");
        }

        $to->loadMissing('roomType');
        $oldPrice = (float) $old->pivot->nightly_rate;
        $newPrice = $keepPrice ? $oldPrice : $to->rate();
        $done = min($reservation->nights(), (int) $reservation->check_in_date->diffInDays(today())); // nights already spent
        $left = $reservation->nights() - $done;

        DB::transaction(function () use ($reservation, $old, $to, $oldPrice, $newPrice, $done, $left, $reason, $by) {
            // The old room's line now covers only the nights already spent (none if moving on the day of arrival)
            $line = $reservation->charges()->where('type', 'room')->where('room_id', $old->id)->latest('id')->first();
            $before = (int) ($line?->nights_before ?? 0);
            if ($line) {
                $nights = max(0, $done - $before);
                $nights > 0
                    ? $line->update(['quantity' => $nights, 'amount' => round($nights * $line->unit_price, 2)])
                    : $line->delete();
            }

            // The rest of the stay on a new line for the new room
            $reservation->charges()->create([
                'room_id' => $to->id,
                'type' => 'room',
                'description' => "Room {$to->number} ({$to->roomType->name}) · moved from {$old->number}",
                'quantity' => $left,
                'nights_before' => $done,
                'unit_price' => $newPrice,
                'amount' => round($left * $newPrice, 2),
                'charge_date' => today(),
                'created_by' => $by->id,
            ]);

            // The guest in the old room goes with the booking to the new one
            $guestId = $old->pivot->guest_id;
            $reservation->rooms()->detach($old->id);
            $reservation->rooms()->attach($to->id, ['nightly_rate' => $newPrice, 'guest_id' => $guestId]);

            $old->update(['status' => 'available', 'housekeeping_status' => 'dirty']); // "Needs cleaning" on the room board
            $to->update(['status' => 'occupied']);

            Activity::log($reservation, 'room_moved', "Room {$old->number} → {$to->number}: {$reason}"
                .' · '.number_format($newPrice).' ETB a night'.($newPrice != $oldPrice ? ' (was '.number_format($oldPrice).')' : ''));
        });
    }
}
