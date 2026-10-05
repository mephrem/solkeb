<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckoutButtonTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function stay(string $room, int $nights, array $extra = []): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays($nights)->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'walk_in',
            'guest' => ['first_name' => 'G'.$room, 'phone' => '0911'.$room],
        ] + $extra)->assertSessionHasNoErrors();
        $r = Reservation::latest('id')->first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');

        return $r->fresh();
    }

    public function test_check_out_is_disabled_until_the_bill_is_paid(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());

        // 3 nights booked at 1,500, leaving early today: 1 night is due, so that is what unlocks check-out
        $r = $this->stay('101', 3);
        $this->assertEquals(4500.0, $r->balance());
        $this->assertEquals(1500.0, $r->dueIfLeavingToday());
        $this->get("/reservations/{$r->id}")->assertSee('Pay 1,500.00 ETB to check out today')
            ->assertSee('disabled title="Record the payment first"', false)
            ->assertDontSee('action="'.route('reservations.check-out', $r).'"', false)
            ->assertSee('value="1500.00"', false) // payment box filled in with what is due today
            ->assertSee('If leaving today (1 night)');

        $this->post("/reservations/{$r->id}/payments", ['amount' => 1500, 'method' => 'cash', 'type' => 'payment'])->assertSessionHas('success');
        $this->get("/reservations/{$r->id}")->assertDontSee('to check out')->assertSee('action="'.route('reservations.check-out', $r).'"', false);
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');

        // An organisation on credit can always check out
        $org = Organization::create(['name' => 'UNICEF', 'credit_allowed' => true]);
        $c = $this->stay('102', 1, ['organization_id' => $org->id, 'on_credit' => 1]);

        // Paid by: the guest (default), or an organisation, the booking's own one first and selected on credit stays
        Organization::create(['name' => 'WFP']);
        $this->get("/reservations/{$c->id}")->assertSee('<option value="" >Guest (self)</option>', false)
            ->assertSee('<option value="UNICEF" selected>UNICEF</option>', false)->assertSee('<option value="WFP" >WFP</option>', false);
        $o2 = $this->stay('104', 1);
        $this->get("/reservations/{$o2->id}")->assertSee('<option value="" selected>Guest (self)</option>', false);
        $this->get("/reservations/{$c->id}")->assertDontSee('to check out')->assertSee('action="'.route('reservations.check-out', $c).'"', false);

        // A guest who stays on past the date owes the extra nights before leaving
        $o = $this->stay('103', 1);
        $this->travel(2)->days();
        $this->assertEquals(3000.0, $o->fresh()->dueIfLeavingToday());
        $this->get("/reservations/{$o->id}")->assertSee('Pay 3,000.00 ETB to check out today');
    }
}
