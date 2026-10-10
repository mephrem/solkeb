<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChoosesBillTo;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Setting;
use App\Rules\PhoneNumber;
use App\Services\Payments\PaymentRecorder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** New booking form: dates, rooms, the guest (existing or typed in), stay details and Bill to. */
class StoreReservationRequest extends FormRequest
{
    use ChoosesBillTo;

    protected function prepareForValidation(): void
    {
        if ($this->has('guest.tin')) {
            $this->merge(['guest' => array_merge($this->input('guest', []), ['tin' => GuestRequest::cleanTin($this->input('guest.tin'))])]);
        }
        if ($this->has('guest.phone')) {
            $this->merge(['guest' => array_merge($this->input('guest', []), ['phone' => PhoneNumber::normalize($this->input('guest.phone'))])]);
        }
    }

    public function rules(): array
    {
        return [
            'check_in_date' => ['required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', 'after:check_in_date'],
            'rooms' => ['required', 'array', 'min:1'],
            'rooms.*' => ['integer', 'exists:rooms,id'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'source' => ['required', Rule::in(array_keys(Reservation::SOURCES))],
            'special_requests' => ['nullable', 'string', 'max:1000'],
            'guest_id' => ['nullable', 'exists:guests,id'],
            // Deposit fields are only used when Hotel settings → "Take a deposit" is on
            'deposit' => ['nullable', 'numeric', 'min:0'],
            'deposit_into' => ['nullable', 'alpha_num', 'max:20'],
            'deposit_method' => ['nullable', Rule::in(array_keys(Payment::METHODS))],
            'deposit_reference' => ['nullable', 'string', 'max:100'],
            'deposit_payer' => ['nullable', 'string', 'max:100'],
        ] + $this->billToRules() + ($this->filled('guest_id') ? [] : GuestRequest::fields('guest.'));
    }

    public function messages(): array
    {
        return ['rooms.required' => 'Choose at least one room.'] + GuestRequest::fieldMessages('guest.');
    }

    /**
     * The deposit to record with the booking, or null (no deposit, or deposits are switched off).
     *
     * @throws ValidationException when the account or transaction number is missing
     */
    public function deposit(PaymentRecorder $payments): ?array
    {
        $amount = (float) $this->validated('deposit');
        if (! Setting::get('take_deposits') || $amount <= 0) {
            return null;
        }

        [$method, $account] = $payments->channel($this->filled('deposit_into') ? (string) $this->input('deposit_into') : null,
            $this->validated('deposit_method'), 'deposit_into');
        $payments->requireReference($method, $account, $this->validated('deposit_reference'), 'deposit_reference');

        return [
            'amount' => $amount,
            'method' => $method,
            'account' => $account,
            'reference' => $this->validated('deposit_reference'),
            'payer_name' => $this->validated('deposit_payer'),
        ];
    }
}
