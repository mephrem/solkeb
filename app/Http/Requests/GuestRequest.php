<?php

namespace App\Http\Requests;

use App\Models\Guest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The guest form (Guests → Add / Edit). The same guest fields are reused, prefixed with "guest.",
 * by the new booking form (see StoreReservationRequest).
 */
class GuestRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('tin')) {
            $this->merge(['tin' => self::cleanTin($this->input('tin'))]);
        }
    }

    public function rules(): array
    {
        return self::fields() + [
            'is_vip' => ['nullable', 'boolean'],
            'is_blacklisted' => ['nullable', 'boolean'],
            'id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            'id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
        ];
    }

    public function messages(): array
    {
        return self::fieldMessages() + [
            'id_front.mimes' => 'The ID front must be a photo (JPG, PNG) or a PDF.',
            'id_back.mimes' => 'The ID back must be a photo (JPG, PNG) or a PDF.',
        ];
    }

    /** The guest's details to save (photos are stored separately). */
    public function guestData(): array
    {
        $data = collect($this->validated())->except(['id_front', 'id_back'])->all();
        $data['is_vip'] = $this->boolean('is_vip');
        $data['is_blacklisted'] = $this->boolean('is_blacklisted');

        return $data;
    }

    /**
     * Rules for a guest's details.
     *
     * TIN and company are no longer on the forms (organisation bookings use the organisation's),
     * but are still accepted so older records and integrations keep working.
     */
    public static function fields(string $prefix = ''): array
    {
        return [
            $prefix.'first_name' => ['required', 'string', 'max:100'],
            $prefix.'father_name' => ['nullable', 'string', 'max:100'],
            $prefix.'grandfather_name' => ['nullable', 'string', 'max:100'],
            $prefix.'gender' => ['nullable', Rule::in(['male', 'female'])],
            $prefix.'phone' => ['required', 'string', 'max:20'],
            $prefix.'email' => ['nullable', 'email', 'max:150'],
            $prefix.'nationality' => ['nullable', 'string', 'max:60'],
            $prefix.'id_type' => ['nullable', Rule::in(array_keys(Guest::ID_TYPES))],
            $prefix.'id_number' => ['nullable', 'string', 'max:50'],
            $prefix.'city' => ['nullable', 'string', 'max:100'],
            $prefix.'company' => ['nullable', 'string', 'max:150'],
            $prefix.'tin' => ['nullable', 'digits:10'],
            $prefix.'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public static function fieldMessages(string $prefix = ''): array
    {
        return [$prefix.'tin.digits' => 'TIN must be exactly 10 digits.'];
    }

    /** "001-234 5678" → "0012345678" */
    public static function cleanTin(?string $tin): ?string
    {
        $tin = preg_replace('/[\s\-\/.]/', '', (string) $tin);

        return $tin === '' ? null : $tin;
    }
}
