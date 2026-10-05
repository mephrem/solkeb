{{--
    Where the money went: cash, or one of the hotel's bank accounts / wallets.
    @include('partials.received-into', ['name' => 'received_into', 'method' => 'method'])
    Before the admin has added any account, the old method list is shown instead.
--}}
@php $accounts = \App\Models\PaymentAccount::active()->orderBy('kind')->orderBy('provider')->orderBy('account_name')->get(); @endphp
@if ($accounts->isEmpty())
    <div class="field"><label>Method</label>
        <select name="{{ $method }}">@foreach (\App\Models\Payment::METHODS as $v => $l)<option value="{{ $v }}" @selected(old($method) === $v)>{{ $l }}</option>@endforeach</select></div>
@else
    <div class="field"><label>Received into *</label>
        <select name="{{ $name }}" required>
            <option value="cash" @selected(old($name, 'cash') === 'cash')>Cash at reception</option>
            @foreach ($accounts->groupBy(fn ($a) => \App\Models\PaymentAccount::KINDS[$a->kind] ?? $a->kind) as $kind => $list)
                <optgroup label="{{ $kind }}">
                    @foreach ($list as $a)<option value="{{ $a->id }}" @selected((string) old($name) === (string) $a->id)>{{ $a->label() }}</option>@endforeach
                </optgroup>
            @endforeach
        </select>
        @error($name)<div class="error-text">{{ $message }}</div>@enderror</div>
@endif
