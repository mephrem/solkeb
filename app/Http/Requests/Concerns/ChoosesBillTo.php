<?php

namespace App\Http\Requests\Concerns;

use App\Models\Organization;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The "Bill to" part of the booking forms: an organisation, and whether it pays later on credit.
 * Only organisations a manager has allowed credit can be billed on credit.
 */
trait ChoosesBillTo
{
    protected function billToRules(): array
    {
        return [
            'organization_id' => ['nullable', Rule::exists('organizations', 'id')->where('is_active', true)],
            'on_credit' => ['nullable', 'boolean'],
        ];
    }

    /** Extra checks after the basic rules pass. */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || ! $this->boolean('on_credit')) {
                return;
            }
            $org = $this->organization();
            if (! $org?->credit_allowed) {
                $validator->errors()->add('on_credit', $org
                    ? "{$org->name} is not allowed credit. A manager can allow it under Organisations."
                    : 'Choose the organisation that will pay on credit.');
            }
        }];
    }

    /** The organisation the booking is billed to, if any. */
    public function organization(): ?Organization
    {
        return $this->filled('organization_id') ? Organization::find($this->input('organization_id')) : null;
    }
}
