<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MyActivityTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function staff(string $name): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@x.com', 'password' => 'password', 'role' => 'receptionist', 'is_active' => true]);
    }

    private function book(string $room, string $guest): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'walk_in',
            'guest' => ['first_name' => $guest, 'phone' => '0911'.$room],
        ])->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_each_receptionist_sees_only_their_own_work(): void
    {
        $hodan = $this->staff('Hodan');
        $sara = $this->staff('Sara');
        $cbe = PaymentAccount::create(['kind' => 'bank', 'provider' => 'CBE', 'account_name' => 'Solkeb', 'account_number' => '1000503354938']);

        // Hodan books and checks in two guests, takes 1,500 cash and 1,500 by bank, checks one out
        $this->actingAs($hodan);
        $a = $this->book('101', 'Abdi');
        $b = $this->book('102', 'Liya');
        foreach ([$a, $b] as $r) {
            $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        }
        $this->post("/reservations/{$a->id}/payments", ['amount' => 1500, 'received_into' => 'cash', 'type' => 'payment'])->assertSessionHas('success');
        $this->post("/reservations/{$b->id}/payments", ['amount' => 1500, 'received_into' => $cbe->id, 'reference' => 'FT1', 'type' => 'payment'])->assertSessionHas('success');
        $this->post("/reservations/{$a->id}/check-out")->assertSessionHas('success');

        // Sara makes one booking
        $this->actingAs($sara);
        $this->book('103', 'Omar');

        $this->actingAs($hodan);
        $res = $this->get('/my-activity')->assertOk()->assertSee('Abdi')->assertSee('Liya')->assertDontSee('Omar')
            ->assertSee('CBE · 1000503354938 · Solkeb');
        $this->assertEquals(['reserved' => 2, 'rooms' => 2, 'checked_in' => 2, 'checked_out' => 1, 'payments' => 2], $res->viewData('counts'));
        $this->assertEquals(1500.0, $res->viewData('cash'));
        $this->assertEquals(3000.0, $res->viewData('total'));

        $this->actingAs($sara);
        $res = $this->get('/my-activity')->assertOk()->assertSee('Omar')->assertDontSee('Abdi');
        $this->assertEquals(['reserved' => 1, 'rooms' => 1, 'checked_in' => 0, 'checked_out' => 0, 'payments' => 0], $res->viewData('counts'));
        $this->assertEquals(0.0, $res->viewData('total'));
        $this->get('/')->assertSee('My activity');
        $this->get('/reports/staff')->assertForbidden(); // the all-staff report stays for managers

        // Other days
        $this->travel(1)->days();
        $this->actingAs($hodan);
        $this->assertEquals(0, $this->get('/my-activity')->viewData('counts')['reserved']);
        $this->assertEquals(2, $this->get('/my-activity?from='.today()->subDay()->toDateString().'&to='.today()->toDateString())->viewData('counts')['reserved']);
    }
}
