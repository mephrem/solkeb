<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Organisation page → Receive payment: one transfer covering the ticked credit invoices. */
class ReceiveOrganizationPaymentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_into' => ['nullable', 'alpha_num', 'max:20'],
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'reference' => ['nullable', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:100'],
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return ['ids.required' => 'Tick the invoices this payment covers.'];
    }

    public function receivedInto(): ?string
    {
        return $this->filled('received_into') ? (string) $this->input('received_into') : null;
    }
}
