<?php

namespace Tests;

use App\Models\Reservation;
use Database\Seeders\TestingSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /** Tests start from fixed test rooms (101–306), not the hotel's real rooms. */
    protected $seeder = TestingSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local'); // ID photos never touch the real storage folder in tests
    }

    /** A complete check-in form: one guest with ID photos per room of the booking. */
    protected function checkInPayload(Reservation $r, array $overrides = []): array
    {
        $occupants = [];
        foreach ($r->rooms()->get() as $i => $room) {
            $occupants[$room->id] = ($overrides[$room->number] ?? []) + [
                'first_name' => $i === 0 ? $r->guest->first_name : 'Guest'.$room->number,
                'phone' => $i === 0 ? $r->guest->phone : '09000'.$room->number.'00',
                'id_type' => 'national_id',
                'id_number' => $i === 0 ? ($r->guest->id_number ?: 'ID-'.$r->guest->id) : 'ID-R'.$room->number,
                'id_front' => UploadedFile::fake()->image("front{$room->number}.jpg", 800, 500),
                'id_back' => UploadedFile::fake()->image("back{$room->number}.jpg", 800, 500),
            ];
        }

        return ['occupants' => $occupants];
    }
}
