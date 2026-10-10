<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Move room: the new room, why, and whether the guest keeps the old price. */
class MoveRoomRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'to_room_id' => ['required', 'integer', 'exists:rooms,id'],
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            'keep_price' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'to_room_id.required' => 'Choose the room the guest is moving to.',
            'reason.required' => 'Give the reason for the move.',
            'reason.min' => 'Give the reason for the move.',
        ];
    }
}
