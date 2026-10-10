<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use App\Rules\PhoneNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhoneAndHalfDayTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function stay(array $rooms, int $nights, string $phone = '0911000001'): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays($nights)->toDateString(),
            'rooms' => Room::whereIn('number', $rooms)->pluck('id')->all(), 'adults' => count($rooms), 'source' => 'walk_in',
            'guest' => ['first_name' => 'Abdi', 'phone' => $phone],
        ])->assertSessionHasNoErrors();
        $r = Reservation::latest('id')->first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');

        return $r->fresh();
    }

    public function test_phone_numbers_are_checked_and_tidied(): void
    {
        foreach (['+251 911 22 33 44' => '0911223344', '251911223344' => '0911223344', '911223344' => '0911223344',
            '0711-223-344' => '0711223344', '00252 63 4123456' => '+252634123456'] as $typed => $stored) {
            $this->assertSame($stored, PhoneNumber::normalize($typed), $typed);
        }

        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $this->post('/guests', ['first_name' => 'Hodan', 'phone' => '0911'])->assertSessionHasErrors('phone');
        $this->post('/guests', ['first_name' => 'Hodan', 'phone' => '0811223344'])->assertSessionHasErrors('phone');
        $this->post('/guests', ['first_name' => 'Hodan', 'phone' => '+251 911 22 33 44'])->assertSessionHasNoErrors();
        $this->assertTrue(Guest::where('phone', '0911223344')->exists());
        $this->post('/guests', ['first_name' => 'Omar', 'phone' => '+252 63 4123456'])->assertSessionHasNoErrors(); // Somaliland / Somalia

        // Booking with the same number typed differently finds the same guest
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id')], 'adults' => 1, 'source' => 'phone',
            'guest' => ['first_name' => 'Hodan', 'phone' => '0911 223 344'],
        ])->assertSessionHasNoErrors();
        $this->assertEquals(1, Guest::where('phone', '0911223344')->count());

        // Check-in: each room's guest needs a valid number
        $r = Reservation::latest('id')->first();
        $bad = $this->checkInPayload($r);
        $bad['occupants'][$r->rooms->first()->id]['phone'] = '12345';
        $this->post("/reservations/{$r->id}/check-in", $bad)->assertSessionHasErrors('occupants.'.$r->rooms->first()->id.'.phone');

        // Organisations may give a landline
        $this->post('/organizations', ['name' => 'Education Office', 'phone' => '025 775 0000', 'credit_days' => 30, 'is_active' => 1])->assertSessionHasNoErrors();
        $this->assertEquals('0257750000', Organization::first()->phone);
        $this->post('/guests', ['first_name' => 'Office', 'phone' => '0257750000'])->assertSessionHasErrors('phone'); // guests: mobile only
    }

    public function test_half_day_extension_until_the_afternoon_for_half_price(): void
    {
        Setting::set('check_out_time', '11:00');
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $r = $this->stay(['101'], 1);   // Standard, 1,500 a night

        // Not on the last day yet: no half day
        $this->post("/reservations/{$r->id}/half-day")->assertSessionHas('error', fn ($m) => str_contains($m, 'checking out today'));

        $this->travelTo(today()->addDay()->setTime(10, 0));
        $this->assertEquals('Leaves today', $r->fresh()->departureLabel());
        $this->travelTo(today()->setTime(12, 0));
        $this->assertEquals('Check-out time passed (11:00)', $r->fresh()->departureLabel());
        $this->get("/reservations/{$r->id}")->assertSee('Leaving today')->assertSee('Half-day extension until 14:30 (+750.00 ETB)');

        $this->post("/reservations/{$r->id}/half-day")->assertSessionHas('success', fn ($m) => str_contains($m, 'until 14:30'));
        $line = $r->charges()->where('type', 'half_day')->first();
        $this->assertEquals([750.0, 'Half-day extension · Room 101 (until 14:30)'], [(float) $line->amount, $line->description]);
        $this->assertEquals('Leaves by 14:30 (half day)', $r->fresh()->departureLabel());
        $this->post("/reservations/{$r->id}/half-day")->assertSessionHas('error', fn ($m) => str_contains($m, 'already'));
        $this->assertStringContainsString('until 14:30', $r->activities()->where('action', 'half_day')->first()->details);

        // Check-out: the night plus the half day; room nights are not changed by it
        $this->assertEquals(2250.0, $r->fresh()->dueIfLeavingToday());
        $this->travelTo(today()->setTime(15, 0));
        $this->assertEquals('Half day ended 14:30', $r->fresh()->departureLabel());
        $this->post("/reservations/{$r->id}/payments", ['amount' => 2250, 'method' => 'cash', 'type' => 'payment']);
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');
        $this->assertEquals(2250.0, $r->fresh()->totalCharges());
    }

    public function test_half_day_for_some_rooms_of_a_group_and_extending_instead(): void
    {
        Setting::set('half_day_percent', '40');
        Setting::set('half_day_until', '15:00');
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $r = $this->stay(['101', '301'], 1);   // 1,500 and 2,500
        $this->travelTo(today()->addDay()->setTime(11, 30));

        $deluxe = Room::where('number', '301')->value('id');
        $this->post("/reservations/{$r->id}/half-day", ['rooms' => [$deluxe]])->assertSessionHas('success');
        $this->assertEquals([1000.0], $r->charges()->where('type', 'half_day')->pluck('amount')->map(fn ($a) => (float) $a)->all()); // 40% of 2,500
        $this->assertFalse($r->fresh()->hasHalfDay()); // room 101 has none

        // The group decides to stay another night: the half day is replaced by the full night
        $this->put("/reservations/{$r->id}", ['check_out_date' => today()->addDay()->toDateString(), 'adults' => 2, 'source' => 'walk_in'])
            ->assertSessionHasNoErrors();
        $this->assertEquals(0, $r->charges()->where('type', 'half_day')->count());
        $this->assertEquals((1500 + 2500) * 2, $r->fresh()->totalCharges());
    }
}
