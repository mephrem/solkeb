<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreChargeRequest;
use App\Models\Activity;
use App\Models\Charge;
use App\Models\Reservation;
use App\Models\Service;

/** Lines on a guest's bill other than room nights: food, laundry, breakages... and discounts. */
class ChargeController extends Controller
{
    public function store(StoreChargeRequest $request, Reservation $reservation)
    {
        if (in_array($reservation->status, Reservation::CLOSED, true)) {
            return back()->with('error', 'This bill is closed.');
        }

        $data = $request->validated();
        $qty = (float) ($data['quantity'] ?? 1);

        if ($data['kind'] === 'service') {
            // Older way of adding: picked from Setup → Services at its listed price
            $service = Service::findOrFail($data['service_id']);
            $line = [
                'service_id' => $service->id,
                'type' => 'service',
                'description' => $service->name,
                'quantity' => $qty,
                'unit_price' => $service->price,
                'amount' => round($qty * $service->price, 2),
            ];
        } else {
            // A charge is price each × quantity; a discount is one amount off the bill
            $discount = $data['kind'] === 'discount';
            $sign = $discount ? -1 : 1;
            $qty = $discount ? 1 : $qty;
            $line = [
                'type' => $data['kind'],
                'description' => $data['description'],
                'quantity' => $qty,
                'unit_price' => $sign * $data['amount'],
                'amount' => round($sign * $data['amount'] * $qty, 2),
            ];
        }

        $charge = $reservation->charges()->create($line + ['charge_date' => today(), 'created_by' => $request->user()->id]);
        Activity::log($reservation, 'charge', $charge->description.($charge->quantity != 1 ? ' × '.rtrim(rtrim(number_format($charge->quantity, 2), '0'), '.') : ''), $charge->amount);

        return back()->with('success', 'Added to the bill.');
    }

    /** Managers only (route middleware): take a line off a bill that is still open. */
    public function destroy(Reservation $reservation, Charge $charge)
    {
        abort_unless($charge->reservation_id === $reservation->id, 404);

        if ($reservation->status === Reservation::CHECKED_OUT) {
            return back()->with('error', 'This bill is closed.');
        }

        Activity::log($reservation, 'charge_removed', $charge->description, $charge->amount);
        $charge->delete();

        return back()->with('success', 'Charge removed.');
    }
}
