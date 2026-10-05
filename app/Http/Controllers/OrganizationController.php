<?php

namespace App\Http\Controllers;

use App\Http\Requests\OrganizationRequest;
use App\Http\Requests\ReceiveOrganizationPaymentRequest;
use App\Models\Guest;
use App\Models\Organization;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Setting;
use App\Services\Payments\OrganizationPaymentService;
use App\Services\Payments\PaymentRecorder;
use Illuminate\Http\Request;

/** Organisations that book rooms, what they owe on credit, and the letters asking them to pay. */
class OrganizationController extends Controller
{
    public function index()
    {
        $orgs = Organization::withCount('reservations')->orderByDesc('is_active')->orderBy('name')->get();
        $owed = $orgs->mapWithKeys(fn ($o) => [$o->id => $o->unpaidCredit()]);

        return view('organizations.index', [
            'orgs' => $orgs->sortByDesc(fn ($o) => $owed[$o->id]->sum(fn ($r) => $r->balance()))->values(),
            'owed' => $owed,
        ]);
    }

    /** Statement: every booking of this organisation, with what is still owed on credit. */
    public function show(Organization $organization)
    {
        $bookings = $organization->reservations()->with(['guest', 'rooms'])->latest('check_in_date')->limit(500)->get();
        $unpaid = $organization->unpaidCredit();

        return view('organizations.show', [
            'org' => $organization,
            'bookings' => $bookings,
            'unpaid' => $unpaid,
            'owed' => $unpaid->sum(fn ($r) => $r->balance()),
        ]);
    }

    /** Formal letter asking the organisation to pay its unpaid credit invoices (all, or the ones ticked). */
    public function paymentRequest(Request $request, Organization $organization)
    {
        $ids = collect($request->query('ids', []))->map(fn ($id) => (int) $id)->filter();
        $items = $organization->unpaidCredit()
            ->filter(fn (Reservation $r) => $r->status === Reservation::CHECKED_OUT && ($ids->isEmpty() || $ids->contains($r->id)))
            ->each->load(['rooms', 'guest'])->values();

        // The guest who stayed in each room (as on the credit invoice), not just the booking contact
        $occupants = Guest::whereIn('id', $items->flatMap(fn ($r) => $r->rooms->pluck('pivot.guest_id'))->filter()->unique())->get()->keyBy('id');

        return view('organizations.payment-request', [
            'org' => $organization,
            'items' => $items->map(fn ($r) => (object) [
                'r' => $r,
                'stays' => $r->rooms->sortBy('number', SORT_NATURAL)
                    ->map(fn ($room) => (object) ['room' => $room->number, 'guest' => ($occupants[$room->pivot->guest_id] ?? $r->guest)->full_name])->values(),
                'total' => $r->totalCharges(), 'paid' => $r->totalPaid(), 'balance' => $r->balance(),
            ]),
            'reference' => Setting::get('booking_prefix', 'SLK').'/PR/'.str_pad((string) $organization->id, 3, '0', STR_PAD_LEFT).'/'.now()->format('ymd'),
            'account' => PaymentAccount::forInvoices(),
        ]);
    }

    /** Receive payment: one transfer from the organisation, shared out over the ticked credit invoices (oldest first). */
    public function receivePayment(ReceiveOrganizationPaymentRequest $request, Organization $organization, PaymentRecorder $payments, OrganizationPaymentService $service)
    {
        [$method, $account] = $payments->channel($request->receivedInto(), $request->validated('method'), 'received_into');
        $payments->requireReference($method, $account, $request->validated('reference'), 'reference');

        $amount = (float) $request->validated('amount');
        $done = $service->receive($organization, $amount, array_map('intval', $request->validated('ids')), $method, $account,
            $request->validated('reference'), $request->validated('payer_name'), $request->user());

        return back()->with('success', number_format($amount, 2).' ETB from '.$organization->name.' recorded: '.implode(', ', $done).'.');
    }

    public function create()
    {
        return view('organizations.form', ['org' => new Organization(['credit_days' => 30, 'is_active' => true])]);
    }

    public function store(OrganizationRequest $request)
    {
        $org = Organization::create($request->organizationData());

        return redirect()->route('organizations.show', $org)->with('success', "{$org->name} added.");
    }

    public function edit(Organization $organization)
    {
        return view('organizations.form', ['org' => $organization]);
    }

    public function update(OrganizationRequest $request, Organization $organization)
    {
        $organization->update($request->organizationData());

        return redirect()->route('organizations.show', $organization)->with('success', "{$organization->name} updated.");
    }

    public function destroy(Organization $organization)
    {
        if ($organization->reservations()->withTrashed()->exists()) {
            return back()->with('error', "{$organization->name} has bookings, so it can't be deleted. Untick Active to hide it.");
        }
        $organization->delete();

        return redirect()->route('organizations.index')->with('success', 'Organisation deleted.');
    }
}
