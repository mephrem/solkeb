<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ChoosesBillTo;
use App\Models\Reservation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edit booking / Extend stay. For a guest already in house only the check-out date can change
 * (the arrival date and rooms are fixed).
 */
class UpdateReservationRequest extends FormRequest
{
    use ChoosesBillTo;

    public function rules(): array
    {
        $inHouse = $this->inHouse();

        return [
            'check_in_date' => [$inHouse ? 'nullable' : 'required', 'date', 'after_or_equal:today'],
            'check_out_date' => ['required', 'date', $inHouse ? 'after:today' : 'after:check_in_date'],
            'rooms' => [$inHouse ? 'nullable' : 'required', 'array', 'min:1'],
            'rooms.*' => ['integer', 'exists:rooms,id'],
            'adults' => ['required', 'integer', 'min:1', 'max:20'],
            'children' => ['nullable', 'integer', 'min:0', 'max:20'],
            'source' => ['required', Rule::in(array_keys(Reservation::SOURCES))],
            'special_requests' => ['nullable', 'string', 'max:1000'],
        ] + $this->billToRules();
    }

    public function messages(): array
    {
        return [
            'rooms.required' => 'Choose at least one room.',
            'check_out_date.after' => 'Check-out must be after '.($this->inHouse() ? 'today' : 'check-in').'.',
        ];
    }

    private function inHouse(): bool
    {
        return $this->route('reservation')->status === Reservation::CHECKED_IN;
    }
}
