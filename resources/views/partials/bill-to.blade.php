{{--
    "Bill to" card on the booking forms: an organisation, and whether it pays later on credit.
    @include('partials.bill-to', ['orgId' => ..., 'credit' => bool])
--}}
@php $orgs = \App\Models\Organization::where('is_active', true)->orderBy('name')->get(); @endphp
<div class="card">
    <h2>Bill to <span class="muted small">(organisation bookings)</span></h2>
    @if ($orgs->isEmpty())
        <p class="small muted" style="margin:0">No organisations yet.@if (auth()->user()->hasRole('admin','manager')) <a href="{{ route('organizations.create') }}">Add one</a> to book for a company, NGO or office.@endif</p>
    @else
        <div class="field"><label>Organisation</label>
            <select name="organization_id" data-credit-org>
                <option value="">— The guest pays —</option>
                @foreach ($orgs as $o)
                    <option value="{{ $o->id }}" data-credit="{{ $o->credit_allowed ? 1 : 0 }}" @selected((string) old('organization_id', $orgId) === (string) $o->id)>{{ $o->name }}{{ $o->credit_allowed ? ' · credit allowed' : '' }}</option>
                @endforeach
            </select></div>
        <label class="check"><input type="checkbox" name="on_credit" value="1" data-credit-box @checked(old('on_credit', $credit))>
            On credit: the organisation pays after the guest leaves</label>
        @error('on_credit')<div class="error-text">{{ $message }}</div>@enderror
        <p class="small muted" style="margin:.4rem 0 0">The guest can check out with the bill unpaid. A credit invoice and a payment request letter are then printed for the organisation.</p>
        <script>
            (function () {
                var sel = document.querySelector('[data-credit-org]'), box = document.querySelector('[data-credit-box]');
                function sync() {
                    var opt = sel.options[sel.selectedIndex], ok = opt && opt.dataset.credit === '1';
                    box.disabled = !ok; if (!ok) box.checked = false;
                }
                sel.addEventListener('change', sync); sync();
            })();
        </script>
    @endif
</div>
