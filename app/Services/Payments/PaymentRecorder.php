<?php

namespace App\Services\Payments;

use App\Models\Activity;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Records money received (or refunded) on a booking, the same way everywhere:
 * the booking payment form, a deposit taken at booking, and an organisation's lump-sum payment.
 */
class PaymentRecorder
{
    /**
     * Turn the "Received into" choice ('cash' or a payment account id) into [method, account].
     *
     * Forms shown before any account was set up send a plain method instead. Once the admin has
     * added accounts, money that is not cash must name the account it went into.
     *
     * @param  string  $field  form field that gets the error message
     * @return array{0: string, 1: ?PaymentAccount}
     *
     * @throws ValidationException
     */
    public function channel(?string $into, ?string $method, string $field): array
    {
        if ($into === 'cash') {
            return ['cash', null];
        }

        if ($into !== null && $into !== '') {
            $account = PaymentAccount::active()->find($into)
                ?? throw ValidationException::withMessages([$field => 'Choose the account the money was received into.']);

            return [$account->method(), $account];
        }

        $method = $method ?: 'cash';
        if ($method !== 'cash' && PaymentAccount::active()->exists()) {
            throw ValidationException::withMessages([$field => 'Choose the bank or wallet account the money was received into.']);
        }

        return [$method, null];
    }

    /**
     * Bank and mobile-money payments need the transaction number, so they can be matched with the statement.
     *
     * @throws ValidationException
     */
    public function requireReference(string $method, ?PaymentAccount $account, ?string $reference, string $field): void
    {
        if (in_array($method, Payment::NEEDS_REFERENCE, true) && blank($reference)) {
            throw ValidationException::withMessages([
                $field => 'Enter the transaction number for '.($account?->provider ?? Payment::METHODS[$method]).'.',
            ]);
        }
    }

    /**
     * Save one payment line on the booking and write it to the booking's history.
     *
     * @param  array{amount: float|string, type: string, method: string, account: ?PaymentAccount, reference?: ?string, payer_name?: ?string, notes?: ?string}  $data
     */
    public function record(Reservation $reservation, array $data, User $by): Payment
    {
        $payment = $reservation->payments()->create([
            'amount' => $data['amount'],
            'type' => $data['type'],
            'method' => $data['method'],
            'payment_account_id' => $data['account']?->id,
            'reference' => $data['reference'] ?? null,
            'payer_name' => ($data['payer_name'] ?? null) ?: null,
            'notes' => $data['notes'] ?? null,
            'paid_at' => now(),
            'received_by' => $by->id,
        ]);

        Activity::log($reservation, $data['type'], $payment->describe(), $data['amount']);

        return $payment;
    }
}
