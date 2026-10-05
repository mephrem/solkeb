<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Add / edit an organisation, including whether it may pay on credit. */
class OrganizationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', Rule::unique('organizations', 'name')->ignore($this->route('organization'))],
            'contact_person' => ['nullable', 'string', 'max:100'],
            'contact_title' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:200'],
            'tin' => ['nullable', 'string', 'max:20'],
            'credit_allowed' => ['nullable', 'boolean'],
            'credit_days' => ['required', 'integer', 'min:0', 'max:365'],
            'notes' => ['nullable', 'string', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /** Validated data with the tick boxes as true/false (unticked boxes are not sent). */
    public function organizationData(): array
    {
        return array_merge($this->validated(), [
            'credit_allowed' => $this->boolean('credit_allowed'),
            'is_active' => $this->boolean('is_active'),
        ]);
    }
}
