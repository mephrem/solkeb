<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMoveTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function stay(string $room, int $nights, string $name): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays($nights)->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'walk_in',
            'guest' => ['first_name' => $name, 'phone' => '0911'.$room.'000'],
        ])->assertSessionHasNoErrors();
        $r = Reservation::latest('id')->first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');

        return $r->fresh();
    }

    private function roomLines(Reservation $r): array
    {
        return $r->charges()->where('type', 'room')->orderBy('id')->get()
            ->map(fn ($c) => [Room::find($c->room_id)->number, (int) $c->quantity, (int) $c->nights_before, (float) $c->unit_price])->all();
    }

    public function test_guest_moves_room_and_the_bill_is_split(): void
    {
        $rec = User::create(['name' => 'Hodan', 'email' => 'hodan@x.com', 'password' => 'password', 'role' => 'receptionist', 'is_active' => true]);
        $this->actingAs($rec);
        $std = Room::where('number', '101')->first();   // Standard 1,500
        $dlx = Room::where('number', '301')->first();   // Deluxe 2,500
        $r = $this->stay('101', 3, 'Abdi');
        $guestId = $r->rooms->first()->pivot->guest_id;

        // Before arrival the room is changed with Edit booking, not here
        $this->travel(1)->days();
        $this->get("/reservations/{$r->id}")->assertSee('Move to another room')->assertSee('301 · 2,500 ETB a night');

        // Checks: reason needed; an occupied room or a room on another booking is refused
        $this->post("/reservations/{$r->id}/rooms/{$std->id}/move", ['to_room_id' => $dlx->id])->assertSessionHasErrors('reason');
        $busy = $this->stay('302', 2, 'Busy');
        $this->post("/reservations/{$r->id}/rooms/{$std->id}/move", ['to_room_id' => $busy->rooms->first()->id, 'reason' => 'Noise'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'not free'));

        // Move after one night, at the new room's price
        $this->post("/reservations/{$r->id}/rooms/{$std->id}/move", ['to_room_id' => $dlx->id, 'reason' => 'AC not working'])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Moved from room 101 to room 301'));

        $r->refresh();
        $this->assertEquals([['101', 1, 0, 1500.0], ['301', 2, 1, 2500.0]], $this->roomLines($r));
        $this->assertEquals(1500 + 5000, $r->totalCharges());
        $this->assertEquals([$dlx->id], $r->rooms->pluck('id')->all());
        $this->assertEquals($guestId, $r->rooms->first()->pivot->guest_id);          // the same guest is in the new room
        $this->assertEquals(['available', 'dirty'], [$std->fresh()->status, $std->fresh()->housekeeping_status]);
        $this->assertEquals('occupied', $dlx->fresh()->status);
        $this->assertStringContainsString('Room 101 → 301: AC not working', $r->activities()->where('action', 'room_moved')->first()->details);
        $this->get("/reservations/{$r->id}")->assertSee('Moved room')->assertSee('moved from 101');
        $this->get('/rooms')->assertOk();

        // Leaving a night early: only the part still running is shortened; the night in 101 stays
        $this->travel(1)->days();
        $this->assertEquals(1500 + 2500.0, $r->fresh()->dueIfLeavingToday());
        $this->post("/reservations/{$r->id}/payments", ['amount' => 4000, 'method' => 'cash', 'type' => 'payment'])->assertSessionHas('success');
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');
        $this->assertEquals([['101', 1, 0, 1500.0], ['301', 1, 1, 2500.0]], $this->roomLines($r->fresh()));

        // The revenue report counts each night in the room it was spent in
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $from = today()->subDays(2)->toDateString();
        $days = $this->actingAs($admin)->get('/reports?from='.$from.'&to='.today()->subDay()->toDateString())->viewData('days');
        $this->assertEquals([1, 1500.0], [$days[0]['sold'], $days[0]['room']]); // first night: room 101 at 1,500, no one else yet
    }

    public function test_keep_the_old_price_and_move_on_the_day_of_arrival(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $std = Room::where('number', '102')->first();
        $dlx = Room::where('number', '303')->first();
        $r = $this->stay('102', 3, 'Liya');

        $this->post("/reservations/{$r->id}/rooms/{$std->id}/move", ['to_room_id' => $dlx->id, 'reason' => 'Upgrade, our fault', 'keep_price' => 1])
            ->assertSessionHas('success');

        // No night spent in 102: its line is gone; all 3 nights in 303 at the old price
        $this->assertEquals([['303', 3, 0, 1500.0]], $this->roomLines($r->fresh()));
        $this->assertEquals(1500.0, (float) $r->fresh()->rooms->first()->pivot->nightly_rate);

        // Extending the stay re-counts the new room's line
        $this->put("/reservations/{$r->id}", ['check_out_date' => today()->addDays(4)->toDateString(), 'adults' => 1, 'source' => 'walk_in'])
            ->assertSessionHasNoErrors();
        $this->assertEquals([['303', 4, 0, 1500.0]], $this->roomLines($r->fresh()));

        // Not checked in: refused
        $this->post('/reservations', [
            'check_in_date' => today()->addDay()->toDateString(), 'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => [Room::where('number', '104')->value('id')], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'Late', 'phone' => '0911999000'],
        ]);
        $future = Reservation::latest('id')->first();
        $this->post("/reservations/{$future->id}/rooms/".Room::where('number', '104')->value('id').'/move', ['to_room_id' => Room::where('number', '105')->value('id'), 'reason' => 'x y z'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Edit booking'));
    }
}
