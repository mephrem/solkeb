<?php

namespace App\Http\Controllers;

use App\Http\Requests\PaymentAccountRequest;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin: the hotel's bank accounts and mobile wallets, and what came into each. */
class PaymentAccountController extends Controller
{
    public function index()
    {
        $month = today()->startOfMonth();
        $signed = "case when type = 'refund' then -amount else amount end";

        // Money in (after refunds) per account: this month and since the account was added
        $totals = Payment::selectRaw("payment_account_id, count(*) as payments, sum($signed) as total, sum(case when paid_at >= ? then $signed else 0 end) as month", [$month])
            ->whereNotNull('payment_account_id')->groupBy('payment_account_id')->get()->keyBy('payment_account_id');

        return view('setup.accounts', [
            'accounts' => PaymentAccount::orderByDesc('is_active')->orderBy('provider')->orderBy('account_name')->get(),
            'totals' => $totals,
            'invoiceAccount' => PaymentAccount::forInvoices(),
            'cashMonth' => (float) Payment::where('method', 'cash')->where('paid_at', '>=', $month)->sum(DB::raw($signed)),
        ]);
    }

    /** Statement: every payment into one account over a period, to compare with the bank statement. */
    public function show(Request $request, PaymentAccount $account)
    {
        $from = Carbon::parse($request->query('from', today()->startOfMonth()->toDateString()))->startOfDay();
        $to = Carbon::parse($request->query('to', today()->toDateString()))->startOfDay();
        if ($to->lt($from)) {
            [$from, $to] = [$to, $from];
        }

        $payments = $account->payments()->with(['reservation.guest', 'receiver'])
            ->whereBetween('paid_at', [$from, $to->copy()->endOfDay()])->orderBy('paid_at')->get();

        return view('setup.account-statement', [
            'account' => $account,
            'payments' => $payments,
            'from' => $from,
            'to' => $to,
            'total' => $payments->sum(fn ($p) => $p->type === 'refund' ? -(float) $p->amount : (float) $p->amount),
        ]);
    }

    public function create()
    {
        return view('setup.account-form', ['account' => new PaymentAccount(['kind' => 'bank', 'is_active' => true])]);
    }

    public function store(PaymentAccountRequest $request)
    {
        $account = PaymentAccount::create($request->accountData());
        $this->saveInvoiceChoice($request->boolean('on_invoices'), $account);

        return redirect()->route('accounts.index')->with('success', "{$account->provider} account {$account->account_number} added.");
    }

    public function edit(PaymentAccount $account)
    {
        return view('setup.account-form', compact('account'));
    }

    public function update(PaymentAccountRequest $request, PaymentAccount $account)
    {
        $account->update($request->accountData());
        $this->saveInvoiceChoice($request->boolean('on_invoices'), $account);

        return redirect()->route('accounts.index')->with('success', "{$account->provider} account {$account->account_number} updated.");
    }

    public function destroy(PaymentAccount $account)
    {
        if ($account->payments()->exists()) {
            return back()->with('error', 'Payments were received into this account, so it can\'t be deleted. Untick Active to stop using it.');
        }

        $account->delete();

        return redirect()->route('accounts.index')->with('success', 'Account deleted.');
    }

    /** Only one account is printed on credit invoices and letters: ticking it here replaces the previous one. */
    private function saveInvoiceChoice(bool $onInvoices, PaymentAccount $account): void
    {
        if ($onInvoices && $account->is_active) {
            Setting::set('invoice_account_id', $account->id);
        } elseif ((string) Setting::get('invoice_account_id') === (string) $account->id) {
            Setting::set('invoice_account_id', null);
        }
    }
}
