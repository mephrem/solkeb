<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Add to bill: a charge (description, price each, quantity) or, for managers, a discount.
 * "service" lines (picked from Setup → Services) are still accepted for older integrations.
 */
class StoreChargeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('kind') !== 'discount' || Gate::allows('give-discount');
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::in(['service', 'other', 'discount'])],
            'service_id' => ['required_if:kind,service', 'nullable', 'exists:services,id'],
            'quantity' => ['nullable', 'numeric', 'min:0.01', 'max:10000'],
            'description' => ['required_unless:kind,service', 'nullable', 'string', 'max:190'],
            'amount' => ['required_unless:kind,service', 'nullable', 'numeric', 'min:0.01'],
        ];
    }
}
