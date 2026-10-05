@extends('layouts.app')
@section('title', $account->exists ? 'Edit account' : 'Add account')
@section('content')
<div class="page-head"><h1>{{ $account->exists ? 'Edit '.$account->provider.' account' : 'Add bank account or wallet' }}</h1></div>
<form class="card" method="POST" action="{{ $account->exists ? route('accounts.update', $account) : route('accounts.store') }}" style="max-width:720px">
    @csrf @if ($account->exists) @method('PUT') @endif
    <div class="fields">
        <div class="field"><label>Type *</label>
            <select name="kind">@foreach (\App\Models\PaymentAccount::KINDS as $v => $l)<option value="{{ $v }}" @selected(old('kind', $account->kind) === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label>Bank or wallet *</label>
            <input type="text" name="provider" list="providers" value="{{ old('provider', $account->provider) }}" placeholder="e.g. Commercial Bank of Ethiopia (CBE)" required>
            <datalist id="providers">@foreach (\App\Models\PaymentAccount::PROVIDERS as $list)@foreach ($list as $name)<option value="{{ $name }}">@endforeach @endforeach</datalist>
            @error('provider')<div class="error-text">{{ $message }}</div>@enderror</div>
    </div>
    <div class="fields">
        <div class="field"><label>Account number / wallet phone *</label><input type="text" name="account_number" value="{{ old('account_number', $account->account_number) }}" required>
            @error('account_number')<div class="error-text">{{ $message }}</div>@enderror</div>
        <div class="field"><label>Account name *</label><input type="text" name="account_name" value="{{ old('account_name', $account->account_name) }}" placeholder="Name the account is registered in" required></div>
    </div>
    <div class="field"><label>Notes</label><input type="text" name="notes" value="{{ old('notes', $account->notes) }}" placeholder="e.g. Main business account, Jigjiga branch"></div>
    <div class="field"><label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $account->is_active))> Active (staff can choose it when recording a payment)</label></div>
    <div class="field"><label class="check"><input type="checkbox" name="on_invoices" value="1" @checked(old('on_invoices', $account->exists && $account->isInvoiceAccount()))> Show on credit invoices and payment request letters</label>
        <div class="small muted">Only one account is printed under "Please pay to". Ticking this replaces the account shown now.</div></div>
    <p class="small muted">You can add several accounts at the same bank or wallet. An account that has received payments can't be deleted; untick Active to stop using it.</p>
    <div class="actions">
        <button class="btn" type="submit">Save</button>
        <a class="btn btn-light" href="{{ route('accounts.index') }}">Cancel</a>
    </div>
</form>
@endsection
