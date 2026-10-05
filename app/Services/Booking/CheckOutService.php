<?php

namespace App\Services\Booking;

use App\Exceptions\BookingException;
use App\Models\Activity;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Checking guests out.
 *
 * The room nights are first re-counted to the nights actually stayed (early departure or overstay).
 * Guests who pay themselves must have settled the bill; an organisation on credit gets a credit invoice instead.
 */
class CheckOutService
{
    public function __construct(private ReservationService $reservations) {}

    /**
     * @return float the balance left after check-out: above zero = owed on credit, below zero = refund due
     *
     * @throws BookingException when the guest is not in house or still owes money
     */
    public function checkOut(Reservation $reservation, User $by): float
    {
        if ($reservation->status !== Reservation::CHECKED_IN) {
            throw new BookingException('Only checked-in guests can be checked out.');
        }

        DB::transaction(function () use ($reservation) {
            $nights = $reservation->nightsSoFar();
            if ($nights !== $reservation->nights()) {
                $reservation->update(['check_out_date' => $reservation->check_in_date->copy()->addDays($nights)]);
                $this->reservations->rebillRoomNights($reservation, $nights);
            }
        });

        $balance = $reservation->balance();
        $onCredit = $reservation->billedOnCredit() && $balance > 0.009;
        if ($balance > 0.009 && ! $onCredit) {
            throw new BookingException('Balance of '.number_format($balance, 2).' ETB must be paid before check-out.');
        }

        DB::transaction(function () use ($reservation, $by, $onCredit, $balance) {
            $reservation->update(['status' => Reservation::CHECKED_OUT, 'checked_out_at' => now(), 'checked_out_by' => $by->id]);

            foreach ($reservation->rooms as $room) {
                $room->update(['status' => 'available', 'housekeeping_status' => 'dirty']); // "Needs cleaning" on the room board
            }
            if ($onCredit) {
                $reservation->issueCreditInvoice();
            }

            Activity::log($reservation, 'checked_out', 'Room '.$reservation->rooms->pluck('number')->join(', ')
                .($onCredit ? " · on credit: {$reservation->organization->name} owes ".number_format($balance, 2).' ETB, invoice '.$reservation->credit_invoice_no : ''));
        });

        return $balance;
    }
}
