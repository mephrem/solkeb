<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartialCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_group_checks_in_without_the_guest_who_has_not_come(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $org = Organization::create(['name' => 'WFP Jijiga', 'credit_allowed' => true]);
        $rooms = Room::whereIn('number', ['201', '202', '203', '204'])->orderBy('number')->get();
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => $rooms->pluck('id')->all(), 'adults' => 4, 'source' => 'corporate',
            'guest' => ['first_name' => 'Ahmed', 'phone' => '0911222000'], 'organization_id' => $org->id, 'on_credit' => 1,
        ])->assertSessionHasNoErrors();
        $r = Reservation::first();
        $this->get("/reservations/{$r->id}")->assertSee('Not arrived yet');

        $late = $rooms->firstWhere('number', '204');
        $payload = $this->checkInPayload($r);
        unset($payload['occupants'][$late->id]); // no details for the guest who has not come

        // Without marking the room, its details are required
        $this->post("/reservations/{$r->id}/check-in", $payload)->assertSessionHasErrors("occupants.{$late->id}.first_name");
        // Marking every room as not arrived is refused
        $this->post("/reservations/{$r->id}/check-in", ['absent' => $rooms->pluck('id')->all()])->assertSessionHas('error');

        $this->post("/reservations/{$r->id}/check-in", $payload + ['absent' => [$late->id]])
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'Room 204 has not arrived'));

        $r->refresh();
        $this->assertEquals('checked_in', $r->status);
        $this->assertEquals(['201', '202', '203'], $r->rooms()->orderBy('number')->pluck('number')->all());
        $this->assertEquals(3, $r->adults);
        $this->assertEquals(3, $r->charges()->where('type', 'room')->count());
        $this->assertEquals('available', $late->fresh()->status);

        // Room 204 is now its own booking: same dates, organisation and credit, still reserved
        $new = Reservation::where('id', '!=', $r->id)->first();
        $this->assertEquals(['confirmed', $org->id, true, 1], [$new->status, $new->organization_id, $new->on_credit, $new->adults]);
        $this->assertEquals([$late->id], $new->rooms->pluck('id')->all());
        $this->assertEquals($r->check_out_date->toDateString(), $new->check_out_date->toDateString());
        $this->assertStringContainsString($new->code, $r->activities()->where('action', 'edited')->first()->details);
        $this->assertFalse(Room::availableBetween(today()->toDateString(), today()->addDay()->toDateString())->whereKey($late->id)->exists());

        // The late guest arrives and is checked in on their own booking
        $this->post("/reservations/{$new->id}/check-in", $this->checkInPayload($new))->assertSessionHas('success');
        $this->assertEquals('occupied', $late->fresh()->status);
    }
}
