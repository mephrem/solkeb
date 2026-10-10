<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** A guest stays past the booked check-out date without the stay being extended. */
class OverstayTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_paying_for_the_nights_actually_stayed_does_not_show_as_credit(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $room = Room::where('number', '101')->first(); // 1,500 a night

        // Booked for 6 nights = 9,000
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(6)->toDateString(),
            'rooms' => [$room->id], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'Abdi', 'phone' => '0911223344'],
        ])->assertSessionHasNoErrors();
        $r = Reservation::first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        $this->assertEquals(9000.0, $r->fresh()->totalCharges());

        // Still here 8 nights later, stay never extended: the payment for 8 nights is recorded straight away
        $this->travel(8)->days();
        $this->post("/reservations/{$r->id}/payments", ['amount' => 12000, 'method' => 'cash', 'type' => 'payment'])->assertSessionHas('success');

        $r->refresh();
        $this->assertEquals(12000.0, $r->totalCharges());          // the bill covers the 8 nights stayed
        $this->assertEquals(0.0, $r->balance());                    // no "credit"
        $this->assertEquals(today()->subDays(2)->toDateString(), $r->check_out_date->toDateString()); // booked date kept…
        $this->assertEquals('Overdue 2 days', $r->departureLabel());                                   // …so it still shows overdue
        $this->assertStringContainsString('2 nights added, 8 nights on the bill', $r->activities()->where('action', 'edited')->first()->details);

        // Opening the booking again adds nothing more the same day; the header shows the real stay
        $this->get("/reservations/{$r->id}")->assertSee('8 nights so far')->assertSee('(booked 6)')->assertSee('Settled');
        $this->assertEquals(12000.0, $r->fresh()->totalCharges());
        $this->assertEquals(1, $r->activities()->where('action', 'edited')->count());

        // Check-out: nothing owed, no refund warning
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');
        $this->assertEquals([8, 12000.0], [$r->fresh()->nights(), $r->fresh()->totalCharges()]);
    }

    public function test_the_bill_grows_each_night_and_a_half_day_becomes_a_full_night(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id')], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'Liya', 'phone' => '0911556677'],
        ]);
        $r = Reservation::first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r));

        // Check-out day: half-day added, but the guest stays the night anyway
        $this->travelTo(today()->addDay()->setTime(12, 0));
        $this->post("/reservations/{$r->id}/half-day")->assertSessionHas('success');
        $this->travelTo(today()->addDay()->setTime(9, 0));
        $this->get("/reservations/{$r->id}")->assertOk();
        $this->assertEquals(0, $r->charges()->where('type', 'half_day')->count());
        $this->assertEquals(3000.0, $r->fresh()->totalCharges()); // 2 nights, no half day

        // One more night: one more on the bill
        $this->travel(1)->days();
        $this->get("/reservations/{$r->id}/invoice")->assertOk();
        $this->assertEquals(4500.0, $r->fresh()->totalCharges());
    }
}
