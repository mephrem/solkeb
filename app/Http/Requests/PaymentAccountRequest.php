<?php

namespace App\Http\Requests;

use App\Models\PaymentAccount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit one of the hotel's bank accounts or mobile wallets. */
class PaymentAccountRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'provider' => trim((string) $this->input('provider')),
            'account_number' => trim((string) $this->input('account_number')),
        ]);
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(array_keys(PaymentAccount::KINDS))],
            'provider' => ['required', 'string', 'max:60'],
            'account_name' => ['required', 'string', 'max:100'],
            // Several accounts at one bank are fine; the same number at the same bank twice is not
            'account_number' => ['required', 'string', 'max:50',
                Rule::unique('payment_accounts')->where('provider', $this->input('provider'))->ignore($this->route('account'))],
            'notes' => ['nullable', 'string', 'max:200'],
            'is_active' => ['nullable', 'boolean'],
            'on_invoices' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['account_number.unique' => 'This account is already in the list.'];
    }

    /** The account's own fields ("on_invoices" is a hotel setting, saved separately). */
    public function accountData(): array
    {
        return array_merge(collect($this->validated())->except('on_invoices')->all(), ['is_active' => $this->boolean('is_active')]);
    }
}
