@extends('layouts.app')
@section('title', $r->code)
@section('content')
@php
    $u = auth()->user();
    $frontDesk = $u->hasRole('admin','manager','receptionist');
    $open = ! in_array($r->status, ['checked_out','cancelled','no_show']);
    $idTypes = \App\Models\Guest::ID_TYPES;
    $group = $r->rooms->count() > 1;
@endphp
<div class="page-head">
    <div>
        <h1>{{ $r->code }} @include('partials.status', ['s' => $r->status])
            @if ($r->group_color)<span class="group-chip" style="--g: {{ $r->group_color }}; font-size:.75rem; vertical-align:middle">Group · {{ $r->rooms->count() }} rooms</span>@endif
            @if ($r->departureLabel())<span class="badge b-due" style="vertical-align:middle">{{ $r->departureLabel() }}</span>@endif</h1>
        <div class="muted">
            {{ $r->check_in_date->format('D d M Y') }} → {{ $r->check_out_date->format('D d M Y') }} ·
            @if ($r->departureState() === 'overdue')
                <strong class="owe">{{ $r->nightsSoFar() }} nights so far</strong> (booked {{ $r->nights() }}) ·
            @else
                {{ $r->nights() }} night{{ $r->nights() == 1 ? '' : 's' }} ·
            @endif
            {{ $r->adults }} adult{{ $r->adults == 1 ? '' : 's' }}@if ($r->children), {{ $r->children }} child{{ $r->children == 1 ? '' : 'ren' }}@endif
            @if ($r->organization) · <a href="{{ route('organizations.show', $r->organization) }}">{{ $r->organization->name }}</a>{{ $r->on_credit ? ' (on credit)' : '' }}@endif
        </div>
    </div>
    <div class="actions">
        @if ($frontDesk && in_array($r->status, ['pending','confirmed','checked_in']))
            <a class="btn btn-light" href="{{ route('reservations.edit', $r) }}">{{ $r->status === 'checked_in' ? 'Extend / change stay' : 'Edit booking' }}</a>
        @endif
        <a class="btn btn-light" href="{{ route('reservations.invoice', $r) }}" target="_blank">Print bill</a>
        @if ($r->on_credit && $r->organization)
            <a class="btn btn-light" href="{{ route('reservations.credit-invoice', $r) }}" target="_blank">Credit invoice</a>
        @endif
        @if ($u->hasRole('admin','manager'))
            <form method="POST" action="{{ route('reservations.destroy', $r) }}" class="inline"
                @if ($r->status === 'checked_out' && $u->cannot('delete-finished-stay'))
                    data-block-title="Can't delete {{ $r->code }}" data-block="This stay is finished and part of the hotel's accounts. Only an admin can delete it."
                @else
                    data-confirm-title="Delete this booking?"
                    data-confirm="{{ $r->code }} · {{ $r->guest->full_name }} · Room {{ $r->rooms->pluck('number')->join(', ') }}.{{ $paid > 0 ? ' Its payments ('.number_format($paid, 2).' ETB) will also be removed from the reports and cash-up.' : '' }}{{ $r->status === 'checked_in' ? ' The room will be freed.' : '' }} It stays in the Deleted bookings log."
                    data-confirm-input="reason" data-confirm-input-label="Why is it being deleted?" data-confirm-input-placeholder="e.g. Duplicate booking, entered by mistake"
                    data-confirm-button="Delete booking" data-confirm-danger
                @endif>
                @csrf @method('DELETE')
                <button class="btn btn-red" type="submit">Delete booking</button>
            </form>
        @endif
        @if ($frontDesk && $r->status === 'checked_in' && $dueToday > 0.009 && ! ($r->on_credit && $r->organization))
            {{-- Guests who pay themselves can only leave once the bill is settled --}}
            <button class="btn" type="button" disabled title="Record the payment first">Check out</button>
            <span class="small owe" style="flex-basis:100%">Pay {{ number_format($dueToday, 2) }} ETB to check out today</span>
        @elseif ($frontDesk && $r->status === 'checked_in')
            <form method="POST" action="{{ route('reservations.check-out', $r) }}" class="inline"
                  data-confirm-title="Check out {{ $r->guest->full_name }}?"
                  data-confirm="{{ $r->on_credit && $r->organization && $balance > 0 ? 'The bill ('.number_format($balance, 2).' ETB after adjusting room nights to the actual stay) goes on a credit invoice to '.$r->organization->name.'.' : ($balance > 0 ? 'Balance still due: '.number_format($balance, 2).' ETB. Room nights will be adjusted to the actual stay.' : 'The bill is settled. Room '.$r->rooms->pluck('number')->join(', ').' will be marked \'Needs cleaning\'.') }}"
                  data-confirm-button="Check out">
                @csrf <button class="btn" type="submit">Check out</button>
            </form>
        @endif
    </div>
</div>

{{-- Check-out day: check-out time, and the half-day extension (late check-out) --}}
@if ($frontDesk && $r->status === 'checked_in' && $r->check_out_date->isToday())
    @php
        $until = \App\Models\Reservation::halfDayUntil();
        $pct = \App\Models\Reservation::halfDayPercent();
        $halfDone = $r->halfDayRoomIds();
        $halfLeft = $r->rooms->reject(fn ($room) => $halfDone->contains($room->id));
        $halfPrice = $halfLeft->sum(fn ($room) => round($room->pivot->nightly_rate * $pct / 100, 2));
    @endphp
    <div class="card" style="border-left:4px solid var(--amber)">
        <div class="card-head"><h2>Leaving today</h2>
            <span class="small muted">check-out time {{ \App\Models\Reservation::checkOutTime() }} · half day until {{ $until }}</span></div>
        @if ($halfLeft->isEmpty())
            <p style="margin:0"><strong>Half-day extension added:</strong> the guest can stay until <strong>{{ $until }}</strong>.
                If they stay the night instead, use <a href="{{ route('reservations.edit', $r) }}">Extend / change stay</a>: the full night replaces the half day.</p>
        @else
            <p class="small muted" style="margin-top:0">Guest staying after {{ \App\Models\Reservation::checkOutTime() }}? Add a half-day extension: they keep the room until {{ $until }} for {{ rtrim(rtrim(number_format($pct, 2), '0'), '.') }}% of one night's price.</p>
            <form method="POST" action="{{ route('reservations.half-day', $r) }}"
                  data-confirm-title="Half-day extension until {{ $until }}?"
                  data-confirm="{{ number_format($halfPrice, 2) }} ETB will be added to the bill for room {{ $halfLeft->pluck('number')->join(', ') }}."
                  data-confirm-button="Add half day">
                @csrf
                @if ($r->rooms->count() > 1)
                    <div class="actions" style="margin-bottom:.6rem">
                        @foreach ($halfLeft as $room)
                            <label class="check small"><input type="checkbox" name="rooms[]" value="{{ $room->id }}" checked> Room {{ $room->number }} · {{ number_format(round($room->pivot->nightly_rate * $pct / 100, 2), 2) }} ETB</label>
                        @endforeach
                    </div>
                @endif
                <button class="btn btn-sm" type="submit">Half-day extension until {{ $until }} (+{{ number_format($halfPrice, 2) }} ETB)</button>
            </form>
        @endif
    </div>
@endif

@if ($r->on_credit && $r->organization && $r->status === 'checked_out' && $balance > 0.009)
    <div class="alert alert-error" style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap">
        <span><strong>On credit:</strong> {{ $r->organization->name }} owes <strong>{{ number_format($balance, 2) }} ETB</strong>
            · invoice {{ $r->credit_invoice_no }}@if ($r->creditDueDate()) · due {{ $r->creditDueDate()->format('d M Y') }}@endif.
            Record their payment below when it arrives.</span>
        <span class="actions">
            <a class="btn btn-sm" href="{{ route('reservations.credit-invoice', $r) }}" target="_blank">Credit invoice</a>
            <a class="btn btn-sm btn-light" href="{{ route('organizations.payment-request', ['organization' => $r->organization, 'ids' => [$r->id]]) }}" target="_blank">Payment request letter</a>
        </span>
    </div>
@endif

@if ($frontDesk && in_array($r->status, ['pending','confirmed']))
    <div class="card" style="border-left:4px solid var(--brand)">
        @if ($r->check_in_date->isAfter(today()))
            <p style="margin:0">Arrives on <strong>{{ $r->check_in_date->format('l d M') }}</strong>. Check-in opens that day.</p>
        @else
            <h2>Check in</h2>
            <p class="small muted" style="margin-top:0">
                @if ($group)
                    Each room needs the person staying in it, with a photo of their ID (front and back).
                    The first room is filled in with the booking contact: change it if someone else stays there.
                @else
                    Check the guest's details and attach a photo of the ID, front and back.
                @endif
                A returning guest whose ID photos are already saved doesn't need new ones.
            </p>
            <form method="POST" action="{{ route('reservations.check-in', $r) }}" enctype="multipart/form-data"
                  data-confirm-title="Check in {{ $group ? $r->rooms->count().' rooms' : $r->guest->full_name }}?"
                  data-confirm="Room {{ $r->rooms->pluck('number')->join(', ') }} · {{ $r->nights() }} night(s). Room charges will be added to the bill." data-confirm-button="Check in">
                @csrf
                @foreach ($r->rooms as $i => $room)
                    @php
                        $n = "occupants[{$room->id}]"; $o = "occupants.{$room->id}.";
                        $g = $i === 0 ? $r->guest : null; // the booking contact, as a starting point for the first room
                    @endphp
                    <fieldset class="occupant" data-occupant @if ($r->group_color) style="border-left: 4px solid {{ $r->group_color }}" @endif>
                        <legend>Room {{ $room->number }} · {{ $room->roomType->name }}
                            @if ($group)<label class="check small" style="display:inline-flex;margin-left:.75rem;font-weight:400">
                                <input type="checkbox" name="absent[]" value="{{ $room->id }}" data-absent @checked(in_array($room->id, old('absent', [])))> Not arrived yet</label>@endif
                        </legend>
                        <div class="fields">
                            <div class="field"><label>First name *</label><input type="text" name="{{ $n }}[first_name]" value="{{ old($o.'first_name', $g?->first_name) }}" required></div>
                            <div class="field"><label>Father's name</label><input type="text" name="{{ $n }}[father_name]" value="{{ old($o.'father_name', $g?->father_name) }}"></div>
                            <div class="field"><label>Phone *</label><input type="tel" name="{{ $n }}[phone]" value="{{ old($o.'phone', $g?->phone) }}" required></div>
                        </div>
                        <div class="fields">
                            <div class="field"><label>ID type *</label>
                                <select name="{{ $n }}[id_type]" required>
                                    @foreach ($idTypes as $v => $l)<option value="{{ $v }}" @selected(old($o.'id_type', $g?->id_type ?? 'national_id') === $v)>{{ $l }}</option>@endforeach
                                </select></div>
                            <div class="field"><label>ID number *</label><input type="text" name="{{ $n }}[id_number]" value="{{ old($o.'id_number', $g?->id_number) }}" required></div>
                            <div class="field"><label>ID front (photo) *</label><input type="file" name="{{ $n }}[id_front]" accept="image/*,application/pdf" data-compress>
                                @error($o.'id_front')<div class="error-text">{{ $message }}</div>@enderror</div>
                            <div class="field"><label>ID back (photo) *</label><input type="file" name="{{ $n }}[id_back]" accept="image/*,application/pdf" data-compress>
                                @error($o.'id_back')<div class="error-text">{{ $message }}</div>@enderror</div>
                        </div>
                        @if ($g && ($g->id_front_path || $g->id_back_path))
                            <div class="small muted" style="margin:-.3rem 0 .6rem">Saved for {{ $g->full_name }}: @include('partials.id-photos', ['guest' => $g])</div>
                        @endif
                    </fieldset>
                @endforeach
                <button class="btn btn-green" type="submit">Check in now</button>
                @if ($group)<p class="small muted" style="margin:.5rem 0 0">If someone in the group has not come, tick <strong>Not arrived yet</strong> on their room. That room moves to a separate booking you can check in later or mark no-show.</p>@endif
            </form>
            <script>
                // A room marked "Not arrived yet" needs no details: grey it out and don't send its fields
                document.querySelectorAll('[data-absent]').forEach(function (box) {
                    function sync() {
                        var set = box.closest('[data-occupant]');
                        set.querySelectorAll('input:not([data-absent]), select').forEach(function (f) { f.disabled = box.checked; });
                        set.style.opacity = box.checked ? .55 : '';
                    }
                    box.addEventListener('change', sync); sync();
                });
            </script>
            <p class="small muted" style="margin:.5rem 0 0">Room charges for {{ $r->nights() }} night(s) are added to the bill at check-in.</p>
        @endif
        <div class="actions" style="margin-top:.75rem">
            <form method="POST" action="{{ route('reservations.cancel', $r) }}" class="inline" data-confirm-title="Cancel this booking?" data-confirm="{{ $r->code }} for {{ $r->guest->full_name }} will be cancelled and the room released." data-confirm-button="Cancel booking" data-confirm-danger>
                @csrf <button class="btn btn-sm btn-red" type="submit">Cancel booking</button>
            </form>
            @if (! $r->check_in_date->isAfter(today()))
            <form method="POST" action="{{ route('reservations.cancel', $r) }}" class="inline" data-confirm-title="Mark as no-show?" data-confirm="{{ $r->guest->full_name }} did not arrive. The room will be released." data-confirm-button="Mark no-show" data-confirm-danger>
                @csrf <input type="hidden" name="as" value="no_show"><button class="btn btn-sm btn-red" type="submit">No-show</button>
            </form>
            @endif
        </div>
    </div>
@endif

<div class="grid grid-main">
    <div>
        <div class="card">
            <div class="card-head"><h2>Bill</h2></div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Item</th><th class="right">Qty</th><th class="right">Price</th><th class="right">Amount</th><th></th></tr></thead>
                <tbody>
                @forelse ($r->charges as $c)
                    <tr>
                        <td class="nowrap small">{{ $c->charge_date->format('d M') }}</td>
                        <td>{{ $c->description }} @if ($c->type === 'room')<span class="small muted">· night(s)</span>@endif</td>
                        <td class="right">{{ rtrim(rtrim(number_format($c->quantity, 2), '0'), '.') }}</td>
                        <td class="right">{{ number_format($c->unit_price, 2) }}</td>
                        <td class="right nowrap {{ $c->amount < 0 ? 'settled' : '' }}">{{ number_format($c->amount, 2) }}</td>
                        <td class="right">
                            @if ($open && $u->hasRole('admin','manager'))
                                <form method="POST" action="{{ route('charges.destroy', [$r, $c]) }}" class="inline" data-confirm-title="Remove this line?" data-confirm="{{ $c->description }}: {{ number_format($c->amount, 2) }} ETB will be taken off the bill." data-confirm-button="Remove" data-confirm-danger>
                                    @csrf @method('DELETE') <button class="btn-link small" type="submit">Remove</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">Nothing charged yet. Room nights are added at check-in.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>

            @if ($open && $frontDesk)
            <details style="margin-top:1rem">
                <summary>+ Add to bill</summary>
                    <form method="POST" action="{{ route('charges.store', $r) }}" style="margin-top:.75rem;max-width:560px">
                        @csrf
                        @can('give-discount')
                            <div class="field"><label>Type</label>
                                <select name="kind"><option value="other">Charge</option><option value="discount">Discount</option></select></div>
                        @else
                            <input type="hidden" name="kind" value="other">
                        @endcan
                        <div class="field"><label>Description</label><input type="text" name="description" placeholder="e.g. Lunch, Laundry, Broken glass" required></div>
                        <div class="fields">
                            <div class="field"><label>Price each (ETB)</label><input type="number" name="amount" step="0.01" min="0.01" required data-price-input></div>
                            <div class="field"><label>Quantity</label><input type="number" name="quantity" value="1" min="1" step="1" data-qty></div>
                        </div>
                        <div class="small muted" data-total style="margin:-.3rem 0 .6rem"></div>
                        <button class="btn btn-sm" type="submit">Add</button>
                    </form>
                <script>
                    // Show price × quantity before the line is added to the bill
                    document.querySelectorAll('form [data-qty]').forEach(function (qty) {
                        var form = qty.form, out = form.querySelector('[data-total]'), price = form.querySelector('[data-price-input]');
                        function show() {
                            var p = parseFloat(price.value), n = parseFloat(qty.value) || 0;
                            out.textContent = p > 0 && n > 0 ? 'Total: ' + (p * n).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ETB' : '';
                        }
                        [qty, price].forEach(function (el) { el.addEventListener('input', show); });
                        show();
                    });
                </script>
            </details>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>Payments</h2></div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>Date</th><th>Type</th><th>Received into</th><th>Reference</th><th>Received by</th><th class="right">Amount</th></tr></thead>
                <tbody>
                @forelse ($r->payments as $p)
                    <tr>
                        <td class="nowrap small">{{ $p->paid_at->format('d M H:i') }}</td>
                        <td>{{ ucfirst($p->type) }}</td>
                        <td>{{ $p->channel() }}@if ($p->payer_name)<div class="small muted">paid by {{ $p->payer_name }}</div>@endif</td>
                        <td class="small">{{ $p->reference }}</td>
                        <td class="small">{{ $p->receiver?->name }}</td>
                        <td class="right nowrap {{ $p->type === 'refund' ? 'owe' : '' }}">{{ $p->type === 'refund' ? '−' : '' }}{{ number_format($p->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No payments yet.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>

            @php $deposits = (bool) \App\Models\Setting::get('take_deposits'); @endphp
            @if (in_array($r->status, ['checked_in','checked_out']) || $paid > 0 || ($deposits && $open))
            <details style="margin-top:1rem" @if ($errors->hasAny(['reference', 'amount', 'received_into'])) open @endif>
                <summary>+ Record payment</summary>
                <form method="POST" action="{{ route('payments.store', $r) }}" style="margin-top:.75rem" data-confirm-payment>
                    @csrf
                    <div class="fields">
                        <div class="field"><label>Amount (ETB)</label><input type="number" name="amount" step="0.01" min="0.01" value="{{ old('amount', ($r->status === 'checked_in' ? $dueToday : $balance) > 0 ? number_format($r->status === 'checked_in' ? $dueToday : $balance, 2, '.', '') : '') }}" required></div>
                        @include('partials.received-into', ['name' => 'received_into', 'method' => 'method'])
                        <div class="field"><label>Type</label>
                            <select name="type">
                                <option value="payment">Payment</option>@if ($deposits)<option value="deposit">Deposit</option>@endif
                                @can('record-refund')<option value="refund">Refund</option>@endcan
                            </select></div>
                        <div class="field"><label>Transaction / receipt no.</label><input type="text" name="reference" value="{{ old('reference') }}">
                            @error('reference')<div class="error-text">{{ $message }}</div>@enderror</div>
                        @include('partials.paid-by', ['name' => 'payer_name', 'org' => $r->organization, 'default' => $r->on_credit && $r->organization ? $r->organization->name : ''])
                    </div>
                    <button class="btn btn-sm btn-green" type="submit">Record payment</button>
                </form>
            </details>
            @endif
        </div>

        <div class="card">
            <div class="card-head"><h2>History</h2><span class="small muted">every action, with the staff member who did it</span></div>
            <div class="table-wrap">
            <table>
                <thead><tr><th>When</th><th>Staff</th><th>What</th><th>Details</th><th class="right">Amount</th></tr></thead>
                <tbody>
                @forelse ($r->activities as $a)
                    <tr>
                        <td class="nowrap small">{{ $a->created_at->format('d M H:i') }}</td>
                        <td class="nowrap"><strong>{{ $a->user?->name ?? '—' }}</strong></td>
                        <td class="nowrap">{{ $a->label() }}</td>
                        <td class="small">{{ $a->details }}</td>
                        <td class="right nowrap">{{ $a->amount !== null ? number_format($a->amount, 2) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No history recorded.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="total-box">
                <div class="total-row"><span>Total charges</span><span>{{ number_format($charged, 2) }}</span></div>
                <div class="total-row"><span>Paid</span><span>{{ number_format($paid, 2) }}</span></div>
                <div class="total-row big">
                    <span>{{ $balance > 0 ? 'Balance due' : ($balance < 0 ? 'Credit' : 'Settled') }}</span>
                    <span class="{{ $balance > 0 ? 'owe' : 'settled' }}">{{ number_format(abs($balance), 2) }} ETB</span>
                </div>
                @if ($r->status === 'checked_in' && abs($dueToday - $balance) > 0.009)
                    @php $sofar = max(1, (int) $r->check_in_date->diffInDays(today())); @endphp
                    <div class="total-row small"><span>If leaving today ({{ $sofar }} night{{ $sofar == 1 ? '' : 's' }})</span>
                        <span class="{{ $dueToday > 0 ? 'owe' : 'settled' }}"><strong>{{ number_format($dueToday, 2) }} ETB</strong></span></div>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h2>{{ $group ? 'Booking contact' : 'Guest' }}</h2>
                <a class="small" href="{{ route('guests.show', $r->guest) }}">Profile</a>
            </div>
            <p style="margin:0 0 .6rem"><strong>{{ $r->guest->full_name }}</strong>
                @if ($r->guest->is_vip) <span class="badge b-vip">VIP</span> @endif
                @if ($r->guest->is_blacklisted) <span class="badge b-cancelled">Blacklisted</span> @endif</p>
            <dl class="dl">
                <dt>Phone</dt><dd>{{ $r->guest->phone }}</dd>
                <dt>ID</dt><dd>{{ $r->guest->id_number ? ($idTypes[$r->guest->id_type] ?? '').' · '.$r->guest->id_number : '—' }}</dd>
                <dt>Nationality</dt><dd>{{ $r->guest->nationality ?: '—' }}</dd>
                @if ($r->guest->company)<dt>Company</dt><dd>{{ $r->guest->company }}</dd>@endif
                @if ($r->guest->tin)<dt>TIN</dt><dd>{{ $r->guest->tin }}</dd>@endif
            </dl>
            @unless ($group)<div style="margin-top:.6rem">@include('partials.id-photos', ['guest' => $r->guest])</div>@endunless
        </div>

        <div class="card">
            <h2>Rooms</h2>
            @foreach ($r->rooms as $room)
                @php $occ = $occupants[$room->pivot->guest_id] ?? null; @endphp
                <div class="room-line" @if ($r->group_color) style="border-left: 4px solid {{ $r->group_color }}" @endif>
                    <div class="total-row"><span><strong>{{ $room->number }}</strong> · {{ $room->roomType->name }}</span><span>{{ number_format($room->pivot->nightly_rate) }} ETB</span></div>
                    @if ($occ && $group)
                        <div class="small"><a href="{{ route('guests.show', $occ) }}">{{ $occ->full_name }}</a> · {{ $occ->phone }}</div>
                        @include('partials.id-photos', ['guest' => $occ])
                    @endif
                    @if ($frontDesk && $r->status === 'checked_in')
                        {{-- Room shifting: only rooms free for the rest of the stay are offered --}}
                        <details class="move-room" @if ($errors->hasAny(['to_room_id', 'reason']) && (int) old('from_room') === $room->id) open @endif>
                            <summary class="small">Move to another room</summary>
                            @if ($moveTo->isEmpty())
                                <p class="small muted">{{ $r->check_out_date->isAfter(today()) ? 'No room is free for the rest of this stay.' : 'The stay ends today: extend it first to move the room.' }}</p>
                            @else
                                <form method="POST" action="{{ route('reservations.move-room', [$r, $room]) }}" style="margin-top:.5rem"
                                      data-confirm-title="Move out of room {{ $room->number }}?"
                                      data-confirm="The guest moves to the room you chose. Room {{ $room->number }} will be marked 'Needs cleaning'. The nights left go on the bill for the new room."
                                      data-confirm-button="Move room">
                                    @csrf
                                    <input type="hidden" name="from_room" value="{{ $room->id }}">
                                    <div class="field"><label>New room</label>
                                        <select name="to_room_id" required>
                                            <option value="">— Choose —</option>
                                            @foreach ($moveTo->groupBy(fn ($m) => $m->roomType->name) as $type => $list)
                                                <optgroup label="{{ $type }}">
                                                    @foreach ($list as $m)<option value="{{ $m->id }}" @selected((string) old('to_room_id') === (string) $m->id)>{{ $m->number }} · {{ number_format($m->rate()) }} ETB a night{{ $m->housekeeping_status === 'dirty' ? ' · needs cleaning' : '' }}</option>@endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                        @error('to_room_id')<div class="error-text">{{ $message }}</div>@enderror</div>
                                    <div class="field"><label>Reason</label>
                                        <input type="text" name="reason" value="{{ old('reason') }}" maxlength="200" placeholder="e.g. AC not working, guest asked for a quieter room" required>
                                        @error('reason')<div class="error-text">{{ $message }}</div>@enderror</div>
                                    <label class="check small"><input type="checkbox" name="keep_price" value="1" @checked(old('keep_price'))> Keep the old price ({{ number_format($room->pivot->nightly_rate) }} ETB a night)</label>
                                    <p class="small muted" style="margin:.3rem 0 .6rem">Otherwise the nights left are charged at the new room's price.</p>
                                    <button class="btn btn-sm" type="submit">Move room</button>
                                </form>
                            @endif
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="card">
            <h2>Who did what</h2>
            <dl class="dl who">
                <dt>Reserved</dt><dd><strong>{{ $r->creator?->name ?? '—' }}</strong> <span class="muted">{{ $r->created_at->format('d M H:i') }}</span></dd>
                @if ($r->checked_in_at)<dt>Checked in</dt><dd><strong>{{ $r->checkedInBy?->name ?? '—' }}</strong> <span class="muted">{{ $r->checked_in_at->format('d M H:i') }}</span></dd>@endif
                @foreach ($r->payments as $p)
                    <dt>{{ $p->type === 'refund' ? 'Refunded' : ($p->type === 'deposit' ? 'Deposit' : 'Payment') }}</dt>
                    <dd><strong>{{ $p->receiver?->name ?? '—' }}</strong> <span class="muted">{{ $p->paid_at->format('d M H:i') }}</span><br>
                        <span class="small">{{ number_format($p->amount, 2) }} ETB · {{ $p->channel() }}</span></dd>
                @endforeach
                @if ($r->checked_out_at)<dt>Checked out</dt><dd><strong>{{ $r->checkedOutBy?->name ?? '—' }}</strong> <span class="muted">{{ $r->checked_out_at->format('d M H:i') }}</span></dd>@endif
                @if (in_array($r->status, ['cancelled', 'no_show']))<dt>{{ $r->status === 'no_show' ? 'No-show' : 'Cancelled' }}</dt><dd><strong>{{ $r->cancelledBy?->name ?? '—' }}</strong> <span class="muted">{{ $r->updated_at->format('d M H:i') }}</span></dd>@endif
                <dt>Booked via</dt><dd>{{ \App\Models\Reservation::SOURCES[$r->source] ?? $r->source }}</dd>
            </dl>
            @if ($r->special_requests)<p class="small" style="margin:.75rem 0 0"><strong>Requests:</strong> {{ $r->special_requests }}</p>@endif
        </div>
    </div>
</div>
@endsection
