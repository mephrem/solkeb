<?php

namespace App\Services\Payments;

use App\Models\Organization;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * One transfer from an organisation that covers several of its credit invoices.
 *
 * It is recorded once and shared out over the chosen invoices, oldest first. Every invoice gets its
 * own payment line with the same account and transaction number, so the bank statement still matches.
 */
class OrganizationPaymentService
{
    public function __construct(private PaymentRecorder $payments) {}

    /**
     * @param  int[]  $invoiceIds  bookings the payment covers
     * @return string[] one line per invoice: "CI-2026-0001 paid in full", "CI-2026-0002 part paid (…)"
     *
     * @throws ValidationException when the amount is more than the chosen invoices add up to
     */
    public function receive(Organization $organization, float $amount, array $invoiceIds, string $method, ?PaymentAccount $account, ?string $reference, ?string $payer, User $by): array
    {
        $invoices = $organization->unpaidCredit()
            ->filter(fn (Reservation $r) => $r->status === Reservation::CHECKED_OUT && in_array($r->id, $invoiceIds))
            ->sortBy('credit_invoiced_at')
            ->values();

        if ($invoices->isEmpty()) {
            throw ValidationException::withMessages(['ids' => 'Tick the invoices this payment covers.']);
        }

        $owed = round($invoices->sum(fn (Reservation $r) => $r->balance()), 2);
        if ($amount > $owed + 0.009) {
            throw ValidationException::withMessages([
                'amount' => 'The ticked invoices come to '.number_format($owed, 2).' ETB. Enter at most that, or tick more invoices.',
            ]);
        }

        return DB::transaction(function () use ($invoices, $amount, $method, $account, $reference, $payer, $organization, $by) {
            $done = [];
            $left = round($amount, 2);

            foreach ($invoices as $invoice) {
                if ($left <= 0.009) {
                    break;
                }
                $due = $invoice->balance();
                $pay = round(min($left, $due), 2);

                $this->payments->record($invoice, [
                    'amount' => $pay,
                    'type' => 'payment',
                    'method' => $method,
                    'account' => $account,
                    'reference' => $reference,
                    'payer_name' => $payer ?: $organization->name,
                    'notes' => 'Part of one organisation payment of '.number_format($amount, 2).' ETB',
                ], $by);

                $left = round($left - $pay, 2);
                $done[] = $invoice->credit_invoice_no.($pay + 0.009 >= $due
                    ? ' paid in full'
                    : ' part paid ('.number_format($due - $pay, 2).' ETB still owed)');
            }

            return $done;
        });
    }
}
