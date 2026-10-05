<?php

namespace App\Http\Requests;

use App\Models\Payment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Record payment on a booking (or a refund, for managers and accountants). */
class StorePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('type') !== 'refund' || Gate::allows('record-refund');
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'received_into' => ['nullable', 'alpha_num', 'max:20'], // 'cash' or a payment account id
            'method' => ['nullable', Rule::in(array_keys(Payment::METHODS))], // only before any account is set up
            'type' => ['required', Rule::in(['deposit', 'payment', 'refund'])],
            'reference' => ['nullable', 'string', 'max:100'],
            'payer_name' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function receivedInto(): ?string
    {
        return $this->filled('received_into') ? (string) $this->input('received_into') : null;
    }
}
