<?php

namespace App\Http\Controllers;

use App\Http\Requests\CheckInRequest;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Charge;
use App\Models\Guest;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Services\Booking\CheckInService;
use App\Services\Booking\CheckOutService;
use App\Services\Booking\ReservationService;
use App\Services\Payments\PaymentRecorder;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Bookings: list, create, change, check in / out, cancel, delete / restore and printed bills.
 * The hotel rules live in App\Services\Booking; this controller only connects them to the pages.
 */
class ReservationController extends Controller
{
    public function __construct(private ReservationService $reservations) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $limit = 3000; // the table searches and pages in the browser; older bookings via the status filter / reports

        $reservations = Reservation::with(['guest', 'rooms', 'creator', 'checkedInBy', 'checkedOutBy', 'payments.receiver'])
            ->when($status, fn ($query) => $query->where('status', $status))
            ->latest('check_in_date')->latest('id')
            ->limit($limit + 1)
            ->get();

        return view('reservations.index', [
            'reservations' => $reservations->take($limit),
            'status' => $status,
            'more' => $reservations->count() > $limit,
        ]);
    }

    /** New booking form, showing only the rooms free for the chosen dates. */
    public function create(Request $request)
    {
        [$checkIn, $checkOut] = $this->dates($request->query('check_in', today()->toDateString()), $request->query('check_out'));

        return view('reservations.create', [
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'nights' => Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)),
            'roomsByType' => $this->freeRooms($checkIn, $checkOut),
            'guest' => $request->query('guest_id') ? Guest::find($request->query('guest_id')) : null,
        ]);
    }

    public function store(StoreReservationRequest $request, PaymentRecorder $payments)
    {
        $reservation = $this->reservations->create(
            $request->validated(),
            $request->organization(),
            $request->deposit($payments),
            $request->user(),
        );

        return redirect()->route('reservations.show', $reservation)->with('success', "Booking {$reservation->code} created.");
    }

    public function show(Reservation $reservation)
    {
        $reservation->load(['guest', 'organization', 'rooms.roomType', 'charges.creator', 'payments.receiver', 'payments.account',
            'creator', 'checkedInBy', 'checkedOutBy', 'cancelledBy', 'activities.user']);

        return view('reservations.show', [
            'r' => $reservation,
            'charged' => $reservation->totalCharges(),
            'paid' => $reservation->totalPaid(),
            'balance' => $reservation->balance(),
            'dueToday' => $reservation->dueIfLeavingToday(),
            'occupants' => $this->occupants($reservation),
        ]);
    }

    /** Edit booking / Extend stay. A guest in house keeps their arrival date and rooms. */
    public function edit(Request $request, Reservation $reservation)
    {
        if (! $reservation->isActive()) {
            return redirect()->route('reservations.show', $reservation)->with('error', 'Closed bookings cannot be changed.');
        }
        $reservation->load(['guest', 'rooms.roomType']);

        $checkIn = $reservation->status === Reservation::CHECKED_IN
            ? $reservation->check_in_date->toDateString()
            : $request->query('check_in', $reservation->check_in_date->toDateString());
        [$checkIn, $checkOut] = $this->dates($checkIn, $request->query('check_out', $reservation->check_out_date->toDateString()));

        $rooms = $this->freeRooms($checkIn, $checkOut, $reservation->id);

        return view('reservations.edit', [
            'r' => $reservation,
            'checkIn' => $checkIn,
            'checkOut' => $checkOut,
            'nights' => Carbon::parse($checkIn)->diffInDays(Carbon::parse($checkOut)),
            'roomsByType' => $rooms,
            'current' => $reservation->rooms->pluck('id')->all(),
            'unavailable' => $reservation->rooms->pluck('id')->diff($rooms->flatten()->pluck('id')),
        ]);
    }

    public function update(UpdateReservationRequest $request, Reservation $reservation)
    {
        $this->reservations->update($reservation, $request->validated(), $request->organization());

        return redirect()->route('reservations.show', $reservation)->with('success', "Booking {$reservation->code} updated.");
    }

    public function checkIn(CheckInRequest $request, Reservation $reservation, CheckInService $checkIn)
    {
        $split = $checkIn->checkIn($reservation, $request->arriving(), $request->absent(), $request->occupants(), $request->photos(), $request->user());

        if ($split) {
            return back()->with('success', 'Guest checked in. Room '.$split->rooms->pluck('number')->join(', ')
                .' has not arrived: it is now booking '.$split->code.'. Check it in when they come, or mark it no-show.');
        }

        return back()->with('success', 'Guest checked in.');
    }

    public function checkOut(Request $request, Reservation $reservation, CheckOutService $checkOut)
    {
        $balance = $checkOut->checkOut($reservation, $request->user());

        if ($balance > 0.009) {
            return back()->with('success', 'Guest checked out on credit. '.number_format($balance, 2).' ETB is owed by '
                .$reservation->organization->name.'. Print the credit invoice and the payment request letter.');
        }

        // Leaving early can leave the guest in credit: make sure staff notice and refund it
        if ($balance < -0.009) {
            return back()->with('error', 'Guest checked out, but they paid '.number_format(-$balance, 2)
                .' ETB more than the bill. Record a refund for that amount.');
        }

        return back()->with('success', 'Guest checked out. The room is marked "Needs cleaning".');
    }

    /** Cancel, or mark no-show ("as=no_show"). */
    public function cancel(Request $request, Reservation $reservation)
    {
        $this->reservations->cancel($reservation, $request->input('as') === 'no_show', $request->user());

        return back()->with('success', 'Booking '.($reservation->status === Reservation::NO_SHOW ? 'marked as no-show' : 'cancelled').'.');
    }

    /** A manager removes a booking made by mistake. It is kept in the Deleted bookings log. */
    public function destroy(Request $request, Reservation $reservation)
    {
        $data = $request->validate(
            ['reason' => ['required', 'string', 'min:3', 'max:300']],
            ['reason.required' => 'Give a reason for deleting this booking.', 'reason.min' => 'Give a reason for deleting this booking.'],
        );

        $this->reservations->delete($reservation, $data['reason'], $request->user());

        return redirect()->route('reservations.index')->with('success', "Booking {$reservation->code} deleted. It is kept in the Deleted bookings log.");
    }

    /** Deleted bookings log, with what each bill and its payments came to. */
    public function deleted()
    {
        $list = Reservation::onlyTrashed()->with(['guest', 'rooms', 'deleter', 'creator'])->latest('deleted_at')->get();
        $ids = $list->pluck('id');

        $charges = Charge::withoutGlobalScope('live')->whereIn('reservation_id', $ids)
            ->selectRaw('reservation_id, sum(amount) as total')->groupBy('reservation_id')->pluck('total', 'reservation_id');
        $paid = Payment::withoutGlobalScope('live')->whereIn('reservation_id', $ids)
            ->selectRaw("reservation_id, sum(case when type = 'refund' then -amount else amount end) as total")
            ->groupBy('reservation_id')->pluck('total', 'reservation_id');

        return view('reservations.deleted', compact('list', 'charges', 'paid'));
    }

    /** The admin undoes a delete, if the rooms are still free. */
    public function restore(int $id)
    {
        $reservation = Reservation::onlyTrashed()->with('rooms')->findOrFail($id);
        $this->reservations->restore($reservation);

        return redirect()->route('reservations.show', $reservation)->with('success', "Booking {$reservation->code} restored.");
    }

    /** Printable bill / invoice for the guest. */
    public function invoice(Reservation $reservation)
    {
        $reservation->load(['guest', 'organization', 'rooms.roomType', 'charges', 'payments.account']);

        return view('reservations.invoice', ['r' => $reservation] + $this->totals($reservation));
    }

    /** Printable invoice addressed to the organisation, for a stay it pays for later. */
    public function creditInvoice(Reservation $reservation)
    {
        abort_unless($reservation->billedOnCredit(), 404);
        $reservation->load(['guest', 'organization', 'rooms.roomType', 'charges', 'payments.account']);

        return view('reservations.credit-invoice', [
            'r' => $reservation,
            'org' => $reservation->organization,
            'occupants' => $this->occupants($reservation),
            'account' => PaymentAccount::forInvoices(),
        ] + $this->totals($reservation));
    }

    /** Check-out must be after check-in: if not, make it the next day. */
    private function dates(string $checkIn, ?string $checkOut): array
    {
        $checkOut ??= Carbon::parse($checkIn)->addDay()->toDateString();
        if ($checkOut <= $checkIn) {
            $checkOut = Carbon::parse($checkIn)->addDay()->toDateString();
        }

        return [$checkIn, $checkOut];
    }

    /** Rooms free for the whole stay, grouped by room type, for the booking forms. */
    private function freeRooms(string $checkIn, string $checkOut, ?int $exceptReservation = null)
    {
        return Room::with('roomType')->availableBetween($checkIn, $checkOut, $exceptReservation)
            ->whereHas('roomType', fn ($q) => $q->where('is_active', true))
            ->orderBy('floor')->orderBy('number')->get()
            ->groupBy(fn ($room) => $room->roomType->name);
    }

    /** The guest staying in each room (group bookings), keyed by guest id. */
    private function occupants(Reservation $reservation)
    {
        return Guest::whereIn('id', $reservation->rooms->pluck('pivot.guest_id')->filter())->get()->keyBy('id');
    }

    /** Totals for printed bills. Prices include VAT; the bill shows how much of the total is VAT. */
    private function totals(Reservation $reservation): array
    {
        $total = $reservation->totalCharges();
        $vat = (float) Setting::get('vat_percent', 15);

        return [
            'total' => $total,
            'paid' => $reservation->totalPaid(),
            'balance' => $reservation->balance(),
            'vatPercent' => $vat,
            'vatAmount' => round($total * $vat / (100 + $vat), 2),
        ];
    }
}
