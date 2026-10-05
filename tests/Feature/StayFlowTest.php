<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StayFlowTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('take_deposits', '1'); // these tests take deposits at booking
    }

    public function test_full_stay(): void
    {
        $admin = User::where('email', 'admin@solkebhotel.com')->first();

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')->assertOk()->assertSee('Solkeb Hotel');
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/');

        foreach (['/', '/rooms', '/reservations', '/reservations/create', '/guests', '/guests/create'] as $url) {
            $this->get($url)->assertOk();
        }

        $room = Room::where('number', '301')->first(); // Deluxe 2500
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(),
            'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => [$room->id], 'adults' => 2, 'source' => 'walk_in',
            'guest' => ['first_name' => 'Abdi', 'father_name' => 'Mohamed', 'phone' => '0915000000'],
            'deposit' => 1000, 'deposit_method' => 'cash',
        ])->assertRedirect();

        $r = Reservation::first();
        $this->assertStringStartsWith('SLK-', $r->code);
        $this->get("/reservations/{$r->id}")->assertOk()->assertSee('Check in now');

        // Room no longer offered for overlapping dates
        $this->assertFalse(Room::availableBetween(today()->addDay()->toDateString(), today()->addDays(3)->toDateString())->where('id', $room->id)->exists());

        // Check-in requires ID
        $this->post("/reservations/{$r->id}/check-in", [])->assertSessionHasErrors("occupants.{$room->id}.id_number");
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r, ['301' => ['id_number' => 'FAYDA123']]))->assertSessionHas('success');
        $this->assertEquals('occupied', $room->fresh()->status);
        $this->assertEquals(5000, $r->fresh()->totalCharges());

        $bf = Service::where('name', 'Breakfast')->first();
        $this->post("/reservations/{$r->id}/charges", ['kind' => 'service', 'service_id' => $bf->id, 'quantity' => 2])->assertSessionHas('success');
        $this->post("/reservations/{$r->id}/charges", ['kind' => 'discount', 'description' => 'Loyalty', 'amount' => 100])->assertSessionHas('success');
        $this->assertEquals(5000 + 600 - 100, $r->fresh()->totalCharges());

        // Other charge: price each × quantity (e.g. 3 glasses at 150)
        $this->post("/reservations/{$r->id}/charges", ['kind' => 'other', 'description' => 'Broken glass', 'amount' => 150, 'quantity' => 3])->assertSessionHas('success');
        $glass = $r->charges()->where('description', 'Broken glass')->first();
        $this->assertEquals([3.0, 150.0, 450.0], [(float) $glass->quantity, (float) $glass->unit_price, (float) $glass->amount]);
        $glass->delete();

        // telebirr needs a reference
        $this->post("/reservations/{$r->id}/payments", ['amount' => 500, 'method' => 'telebirr', 'type' => 'payment'])->assertSessionHasErrors('reference');

        // Early checkout (today = check-in day → 1 night): room re-billed to 2500
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('error');
        $r->refresh();
        $this->assertEquals(2500 + 600 - 100, $r->totalCharges());
        $this->assertEquals(2000, $r->balance());

        $this->post("/reservations/{$r->id}/payments", ['amount' => 2000, 'method' => 'telebirr', 'type' => 'payment', 'reference' => 'TB99'])->assertSessionHas('success');
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');

        // Overpaid guest: checked out, but staff are told to refund
        $r2room = Room::where('number', '302')->first();
        $this->post('/reservations', ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => [$r2room->id], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'Sara', 'phone' => '0917000000'],
            'deposit' => 5000, 'deposit_method' => 'cash']);
        $r2 = Reservation::latest('id')->first();
        $this->post("/reservations/{$r2->id}/check-in", $this->checkInPayload($r2));
        $this->post("/reservations/{$r2->id}/check-out")->assertSessionHas('error', fn ($m) => str_contains($m, '2,500.00 ETB more'));
        $this->assertEquals('checked_out', $r2->fresh()->status);
        $this->assertEquals('checked_out', $r->fresh()->status);
        $this->assertEquals('dirty', $room->fresh()->housekeeping_status);
        $this->assertEquals('available', $room->fresh()->status);

        $this->get("/reservations/{$r->id}/invoice")->assertOk()->assertSee('Invoice')->assertSee('391.30');
        $this->get('/')->assertOk()->assertSee('8,000'); // collected today: 3,000 + Sara's 5,000 deposit
        $this->get('/rooms')->assertOk();

        // Reception marks the room clean
        $this->patch("/rooms/{$room->id}", ['housekeeping_status' => 'clean']);
        $this->assertEquals('clean', $room->fresh()->housekeeping_status);

    }
}
