<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Activity;
use App\Models\Guest;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use App\Services\Payments\PaymentRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Making, changing, cancelling, deleting and restoring bookings.
 * Check-in and check-out have their own services (CheckInService, CheckOutService).
 */
class ReservationService
{
    public function __construct(private PaymentRecorder $payments) {}

    /**
     * Create a confirmed booking.
     *
     * @param  array  $data  validated StoreReservationRequest data
     * @param  array|null  $deposit  ['amount', 'method', 'account', 'reference', 'payer_name'] when a deposit is taken
     *
     * @throws BookingException when a room was booked by someone else in the meantime
     */
    public function create(array $data, ?Organization $org, ?array $deposit, User $by): Reservation
    {
        $this->ensureFree($data['rooms'], $data['check_in_date'], $data['check_out_date'],
            'Room(s) :rooms were just booked by someone else. Please choose again.');

        return DB::transaction(function () use ($data, $org, $deposit, $by) {
            $guest = $this->guestFor($data);
            if ($org && ! $guest->company) {
                $guest->update(['company' => $org->name]);
            }

            $reservation = Reservation::create([
                'guest_id' => $guest->id,
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'adults' => $data['adults'],
                'children' => $data['children'] ?? 0,
                'source' => $data['source'],
                'special_requests' => $data['special_requests'] ?? null,
                'organization_id' => $org?->id,
                'on_credit' => $org && ! empty($data['on_credit']),
                'status' => Reservation::CONFIRMED,
                'created_by' => $by->id,
            ]);

            // The nightly price is fixed at booking time, so later price changes don't affect it
            foreach (Room::with('roomType')->whereIn('id', $data['rooms'])->get() as $room) {
                $reservation->rooms()->attach($room->id, ['nightly_rate' => $room->rate()]);
            }
            $reservation->assignGroupColor();

            Activity::log($reservation, 'reserved', 'Room '.$this->roomNumbers($reservation).', '.$this->dates($reservation));

            if ($deposit) {
                $this->payments->record($reservation, $deposit + ['type' => 'deposit'], $by);
            }

            return $reservation;
        });
    }

    /**
     * Change dates, rooms and stay details. For a guest already in house only the check-out date
     * can move, and the room nights on the bill are re-counted.
     *
     * @throws BookingException when the booking is closed or a room is not free for the new dates
     */
    public function update(Reservation $reservation, array $data, ?Organization $org): void
    {
        if (! $reservation->isActive()) {
            throw new BookingException('Closed bookings cannot be changed.');
        }

        $inHouse = $reservation->status === Reservation::CHECKED_IN;
        $checkIn = $inHouse ? $reservation->check_in_date->toDateString() : $data['check_in_date'];
        $roomIds = $inHouse ? $reservation->rooms->pluck('id')->all() : array_map('intval', $data['rooms']);

        $this->ensureFree($roomIds, $checkIn, $data['check_out_date'], 'Room :rooms is not free for those dates.', $reservation->id);

        DB::transaction(function () use ($reservation, $data, $checkIn, $roomIds, $inHouse, $org) {
            $reservation->update([
                'organization_id' => $org?->id,
                'on_credit' => $org && ! empty($data['on_credit']),
                'check_in_date' => $checkIn,
                'check_out_date' => $data['check_out_date'],
                'adults' => $data['adults'],
                'children' => $data['children'] ?? 0,
                'source' => $data['source'],
                'special_requests' => $data['special_requests'] ?? null,
            ]);

            if ($inHouse) {
                // Longer or shorter stay: re-bill the room nights
                $this->rebillRoomNights($reservation, $reservation->fresh()->nights());
            } else {
                // Keep the booked price for rooms that stay on the booking; added rooms get today's price
                $keep = $reservation->rooms->pluck('pivot.nightly_rate', 'id');
                $sync = [];
                foreach (Room::with('roomType')->whereIn('id', $roomIds)->get() as $room) {
                    $sync[$room->id] = ['nightly_rate' => $keep[$room->id] ?? $room->rate()];
                }
                $reservation->rooms()->sync($sync);
                $reservation->assignGroupColor();
            }

            $reservation->refresh();
            Activity::log($reservation, 'edited', 'Room '.$this->roomNumbers($reservation).', '.$this->dates($reservation));
        });
    }

    /**
     * Cancel a booking, or mark it no-show when the guest never came. The rooms are released.
     *
     * @throws BookingException when the guest has already checked in
     */
    public function cancel(Reservation $reservation, bool $noShow, User $by): void
    {
        if (! $reservation->isUpcoming()) {
            throw new BookingException('Only bookings that have not checked in can be cancelled.');
        }

        $reservation->update(['status' => $noShow ? Reservation::NO_SHOW : Reservation::CANCELLED, 'cancelled_by' => $by->id]);
        Activity::log($reservation, $reservation->status);
    }

    /**
     * Remove a booking made by mistake. It is soft-deleted and stays in the Deleted bookings log.
     *
     * @throws BookingException when a finished stay is deleted by someone other than the admin
     */
    public function delete(Reservation $reservation, string $reason, User $by): void
    {
        if ($reservation->status === Reservation::CHECKED_OUT && $by->cannot('delete-finished-stay')) {
            throw new BookingException('This stay is finished and part of the accounts. Only an admin can delete it.');
        }

        DB::transaction(function () use ($reservation, $reason, $by) {
            if ($reservation->status === Reservation::CHECKED_IN) {
                $reservation->rooms()->update(['status' => 'available']); // free the room straight away
            }
            $reservation->forceFill(['deleted_by' => $by->id, 'delete_reason' => $reason])->save();
            Activity::log($reservation, 'deleted', $reason);
            $reservation->delete();
        });
    }

    /**
     * Undo a delete, if the booking's rooms have not been given to someone else meanwhile.
     *
     * @throws BookingException when a room is taken
     */
    public function restore(Reservation $reservation): void
    {
        if ($reservation->isActive()) {
            $roomIds = $reservation->rooms->pluck('id');
            $free = Room::availableBetween($reservation->check_in_date->toDateString(), $reservation->check_out_date->toDateString())
                ->whereIn('id', $roomIds)->pluck('id');
            $taken = $roomIds->diff($free);
            if ($reservation->status === Reservation::CHECKED_IN) {
                $taken = $taken->merge($reservation->rooms->where('status', 'occupied')->pluck('id'))->unique();
            }
            if ($taken->isNotEmpty()) {
                throw new BookingException('Room '.Room::whereIn('id', $taken)->pluck('number')->join(', ')
                    .' has been given to someone else, so this booking cannot be restored.');
            }
        }

        DB::transaction(function () use ($reservation) {
            $reservation->restore();
            Activity::log($reservation, 'restored');
            $reservation->forceFill(['deleted_by' => null, 'delete_reason' => null])->save();
            if ($reservation->status === Reservation::CHECKED_IN) {
                $reservation->rooms()->update(['status' => 'occupied']);
            }
        });
    }

    /** Set the room lines on the bill to this many nights. */
    public function rebillRoomNights(Reservation $reservation, int $nights): void
    {
        foreach ($reservation->charges()->where('type', 'room')->get() as $charge) {
            $charge->update(['quantity' => $nights, 'amount' => round($nights * $charge->unit_price, 2)]);
        }
    }

    /**
     * Someone may have booked the same room since the form was opened.
     *
     * @param  string  $message  with ":rooms" where the room numbers go
     *
     * @throws BookingException
     */
    private function ensureFree(array $roomIds, string $checkIn, string $checkOut, string $message, ?int $exceptReservation = null): void
    {
        $free = Room::availableBetween($checkIn, $checkOut, $exceptReservation)->whereIn('id', $roomIds)->pluck('id');
        $taken = collect($roomIds)->diff($free);

        if ($taken->isNotEmpty()) {
            throw new BookingException(str_replace(':rooms', Room::whereIn('id', $taken)->pluck('number')->join(', '), $message));
        }
    }

    /** An existing guest, or the one typed in: the same phone number means the same guest, so repeat guests are not duplicated. */
    private function guestFor(array $data): Guest
    {
        if (! empty($data['guest_id'])) {
            return Guest::findOrFail($data['guest_id']);
        }

        $guest = Guest::firstOrNew(['phone' => $data['guest']['phone']]);
        $guest->fill(array_filter($data['guest'], fn ($v) => $v !== null && $v !== ''))->save();

        return $guest;
    }

    private function roomNumbers(Reservation $reservation): string
    {
        return $reservation->rooms()->orderBy('number')->pluck('number')->join(', ');
    }

    private function dates(Reservation $reservation): string
    {
        return $reservation->check_in_date->format('d M').' – '.$reservation->check_out_date->format('d M Y');
    }
}
