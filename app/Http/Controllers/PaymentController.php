<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Models\Reservation;
use App\Services\Payments\PaymentRecorder;

/** Money received on a booking (or given back, for managers and accountants). */
class PaymentController extends Controller
{
    public function store(StorePaymentRequest $request, Reservation $reservation, PaymentRecorder $payments)
    {
        // Which account the money went into (or came out of, for a refund), and its transaction number
        [$method, $account] = $payments->channel($request->receivedInto(), $request->validated('method'), 'received_into');
        $payments->requireReference($method, $account, $request->validated('reference'), 'reference');

        $payments->record($reservation, [
            'amount' => $request->validated('amount'),
            'type' => $request->validated('type'),
            'method' => $method,
            'account' => $account,
            'reference' => $request->validated('reference'),
            'payer_name' => $request->validated('payer_name'),
            'notes' => $request->validated('notes'),
        ], $request->user());

        return back()->with('success', 'Payment of '.number_format($request->validated('amount'), 2).' ETB recorded.');
    }
}
