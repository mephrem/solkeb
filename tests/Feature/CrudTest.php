<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function book(string $room, int $in, int $out, string $phone = '0911000011'): Reservation
    {
        $this->post('/reservations', ['check_in_date' => today()->addDays($in)->toDateString(), 'check_out_date' => today()->addDays($out)->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'phone',
            'guest' => ['first_name' => 'Abdi', 'phone' => $phone]])->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_deletes_and_their_guards(): void
    {
        Cache::flush();
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $this->actingAs($admin);

        // Every list page renders its delete buttons
        foreach (['/setup/rooms', '/setup/services', '/setup/users'] as $url) {
            $this->get($url)->assertOk()->assertSee('Delete');
        }

        // Room type with rooms: blocked in the page and on the server
        $std = RoomType::where('name', 'Standard')->first();
        $this->get('/setup/rooms')->assertSee("Can't delete Standard", false);
        $this->delete("/setup/room-types/{$std->id}")->assertSessionHas('error');
        $empty = RoomType::create(['name' => 'Empty', 'base_rate' => 1]);
        $this->delete("/setup/room-types/{$empty->id}")->assertSessionHas('success');

        // Service never sold: deleted; sold: blocked
        $water = Service::where('name', 'Bottled water')->first();
        $this->delete("/setup/services/{$water->id}")->assertSessionHas('success');
        $this->assertNull(Service::find($water->id));
        $r = $this->book('101', 0, 1);
        $bf = Service::where('name', 'Breakfast')->first();
        $this->post("/reservations/{$r->id}/charges", ['kind' => 'service', 'service_id' => $bf->id, 'quantity' => 1]);
        $this->delete("/setup/services/{$bf->id}")->assertSessionHas('error');

        // Guest with bookings: blocked; without: deleted
        $this->delete("/guests/{$r->guest_id}")->assertSessionHas('error');
        $lone = Guest::create(['first_name' => 'Lone', 'phone' => '0999000099']);
        $this->delete("/guests/{$lone->id}")->assertRedirect('/guests');
        $this->assertNull(Guest::find($lone->id));

        // Staff: self and people with history are blocked; a fresh account can go
        $this->delete("/setup/users/{$admin->id}")->assertSessionHas('error');
        $new = User::create(['name' => 'New', 'email' => 'n@x.com', 'password' => 'secret123', 'role' => 'receptionist']);
        $this->delete("/setup/users/{$new->id}")->assertSessionHas('success');
        $rec = User::create(['name' => 'Rec', 'email' => 'r@x.com', 'password' => 'secret123', 'role' => 'receptionist']);
        $this->actingAs($rec)->post("/reservations/{$r->id}/payments", ['amount' => 10, 'method' => 'cash', 'type' => 'payment']);
        $this->actingAs($admin)->delete("/setup/users/{$rec->id}")->assertSessionHas('error');
        $this->get('/setup/users')->assertSee("Can't delete Rec", false);

    }

    public function test_edit_booking_and_extend_stay(): void
    {
        Cache::flush();
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());

        $a = $this->book('101', 1, 3);                // 101 booked tomorrow for 2 nights
        $this->book('102', 3, 5, '0922000022');             // 102 taken later

        $this->get("/reservations/{$a->id}/edit")->assertOk()->assertSee('booked rate');

        // Move to 102 over dates where 102 is taken -> refused
        $this->put("/reservations/{$a->id}", ['check_in_date' => today()->addDays(2)->toDateString(), 'check_out_date' => today()->addDays(4)->toDateString(),
            'rooms' => [Room::where('number', '102')->value('id')], 'adults' => 2, 'source' => 'phone'])->assertSessionHas('error');

        // Change dates on the same room (its own booking doesn't count as a clash) and add a room
        Room::where('number', '101')->first()->roomType->update(['base_rate' => 1900]); // price rise after booking
        $this->put("/reservations/{$a->id}", ['check_in_date' => today()->addDays(2)->toDateString(), 'check_out_date' => today()->addDays(5)->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id'), Room::where('number', '103')->value('id')], 'adults' => 3, 'source' => 'phone'])
            ->assertRedirect("/reservations/{$a->id}");
        $a->refresh()->load('rooms');
        $this->assertEquals(3, $a->nights());
        $this->assertEquals(['101' => 1500.0, '103' => 1900.0], $a->rooms->mapWithKeys(fn ($r) => [$r->number => (float) $r->pivot->nightly_rate])->all());

        // In-house guest extends: room nights re-billed
        $b = $this->book('201', 0, 2, '0933000033');
        $this->post("/reservations/{$b->id}/check-in", $this->checkInPayload($b));
        $this->assertEquals(3800, $b->fresh()->totalCharges()); // Standard now 1,900 x 2
        $this->put("/reservations/{$b->id}", ['check_out_date' => today()->addDays(4)->toDateString(), 'adults' => 1, 'source' => 'walk_in'])->assertSessionHas('success');
        $this->assertEquals(7600, $b->fresh()->totalCharges());
        // Extension that runs into another booking is refused
        $this->book('201', 5, 6, '0944000044');
        $this->put("/reservations/{$b->id}", ['check_out_date' => today()->addDays(7)->toDateString(), 'adults' => 1, 'source' => 'walk_in'])->assertSessionHas('error');
        $this->assertEquals(7600, $b->fresh()->totalCharges());

        // Closed bookings can't be edited
        $a->update(['status' => 'cancelled']);
        $this->get("/reservations/{$a->id}/edit")->assertRedirect("/reservations/{$a->id}");
    }
}
