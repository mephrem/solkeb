<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GuestTinTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_tin_on_guest_booking_and_bill(): void
    {
        Cache::flush();
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());

        // Guest form: spaces/dashes are cleaned; wrong length is refused
        // The TIN box is no longer on the guest form (organisation bookings use the organisation's TIN), but older guest TINs still work
        $this->post('/guests', ['first_name' => 'Save the Children', 'phone' => '0911000011', 'company' => 'Save the Children', 'tin' => '12345'])
            ->assertSessionHasErrors(['tin' => 'TIN must be exactly 10 digits.']);
        $this->post('/guests', ['first_name' => 'Amina', 'phone' => '0911000011', 'company' => 'Save the Children', 'tin' => '000 123-4567'])->assertSessionHasNoErrors();
        $g = Guest::where('phone', '0911000011')->first();
        $this->assertEquals('0001234567', $g->tin);
        $this->get("/guests/{$g->id}")->assertSee('0001234567');
        $this->get('/guests')->assertSee('TIN 0001234567');

        // Edit: clearing the TIN is allowed
        $this->put("/guests/{$g->id}", ['first_name' => 'Amina', 'phone' => '0911000011', 'tin' => ''])->assertSessionHasNoErrors();
        $this->assertNull($g->fresh()->tin);

        // New booking with a TIN for a new guest
        $this->post('/reservations', ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id')], 'adults' => 1, 'source' => 'corporate',
            'guest' => ['first_name' => 'Yusuf', 'phone' => '0922000022', 'company' => 'UNHCR Jijiga', 'tin' => '12a4567890']])
            ->assertSessionHasErrors(['guest.tin' => 'TIN must be exactly 10 digits.']);
        $this->post('/reservations', ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id')], 'adults' => 1, 'source' => 'corporate',
            'guest' => ['first_name' => 'Yusuf', 'phone' => '0922000022', 'company' => 'UNHCR Jijiga', 'tin' => '0098765432']])->assertSessionHasNoErrors();
        $r = Reservation::latest('id')->first();
        $this->assertEquals('0098765432', $r->guest->tin);

        // Booking page and printed bill show it
        $this->get("/reservations/{$r->id}")->assertSee('0098765432');
        $this->get("/reservations/{$r->id}/invoice")->assertSee('UNHCR Jijiga')->assertSee('TIN: <strong>0098765432</strong>', false);
    }
}
