<?php

namespace App\Http\Requests;

use App\Exceptions\BookingException;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\Booking\CheckInService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Check-in form: for every room, the guest staying in it with their ID details and photos.
 * On a group booking a room can be ticked "Not arrived yet" (absent[]): it needs no details.
 */
class CheckInRequest extends FormRequest
{
    /**
     * Stop before validating the form when the booking can't be checked in at all.
     *
     * @throws BookingException
     */
    protected function prepareForValidation(): void
    {
        app(CheckInService::class)->ensureCanCheckIn($this->reservation());

        if ($this->arriving()->isEmpty()) {
            throw new BookingException('No room is marked as arrived. If nobody came, use No-show instead.');
        }
    }

    public function rules(): array
    {
        $rules = [];
        foreach ($this->arriving() as $room) {
            $p = "occupants.{$room->id}.";
            $rules += [
                $p.'first_name' => ['required', 'string', 'max:100'],
                $p.'father_name' => ['nullable', 'string', 'max:100'],
                $p.'phone' => ['required', 'string', 'max:20'],
                $p.'id_type' => ['required', Rule::in(array_keys(Guest::ID_TYPES))],
                $p.'id_number' => ['required', 'string', 'max:50'],
                $p.'id_front' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
                $p.'id_back' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:8192'],
            ];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'occupants.*.*.mimes' => ':attribute must be a photo (JPG, PNG) or a PDF.',
            'occupants.*.*.max' => ':attribute is too big (max 8 MB).',
        ];
    }

    /** "Room 201: first name" instead of "occupants.12.first_name" in error messages. */
    public function attributes(): array
    {
        $names = [];
        foreach ($this->arriving() as $room) {
            $p = "occupants.{$room->id}.";
            foreach (['first_name' => 'first name', 'phone' => 'phone', 'id_type' => 'ID type', 'id_number' => 'ID number', 'id_front' => 'ID front', 'id_back' => 'ID back'] as $field => $label) {
                $names[$p.$field] = "Room {$room->number}: {$label}";
            }
        }

        return $names;
    }

    public function reservation(): Reservation
    {
        return $this->route('reservation')->loadMissing('rooms.roomType');
    }

    /**
     * Rooms ticked "Not arrived yet" (only possible when the booking has more than one room).
     *
     * @return Collection<int, int>
     */
    public function absent(): Collection
    {
        $rooms = $this->reservation()->rooms;

        return $rooms->count() > 1
            ? collect($this->input('absent', []))->map(fn ($id) => (int) $id)->intersect($rooms->pluck('id'))->values()
            : collect();
    }

    /** @return Collection<int, Room> rooms whose guest is here now */
    public function arriving(): Collection
    {
        $absent = $this->absent();

        return $this->reservation()->rooms->reject(fn ($room) => $absent->contains($room->id))->values();
    }

    /** @return array<int, array> validated guest details per room id */
    public function occupants(): array
    {
        return $this->validated('occupants', []);
    }

    /** @return array<int, array{front: ?UploadedFile, back: ?UploadedFile}> ID photos per room id */
    public function photos(): array
    {
        return $this->arriving()->mapWithKeys(fn ($room) => [$room->id => [
            'front' => $this->file("occupants.{$room->id}.id_front"),
            'back' => $this->file("occupants.{$room->id}.id_back"),
        ]])->all();
    }
}
