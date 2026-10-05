<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakfastListTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function book(string $room, string $name, int $nights, int $adults, int $children = 0): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays($nights)->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => $adults, 'children' => $children, 'source' => 'walk_in',
            'guest' => ['first_name' => $name, 'phone' => '09110'.$room],
        ])->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_breakfast_list_shows_who_slept_here_the_night_before(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $stay = $this->book('101', 'Abdi', 2, 2, 1);
        $leaver = $this->book('102', 'Hodan', 1, 1);
        $this->book('103', 'Expected', 1, 1); // booked for today but never arrives
        foreach ([$stay, $leaver] as $r) {
            $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        }

        // Today: nobody slept here last night yet. Tomorrow's list (printed tonight) shows the in-house guests and the expected arrival
        $this->get('/breakfast')->assertOk()->assertSee('No guests stayed the night before');
        $this->get('/breakfast')->assertDontSee('id="quickbar"', false); // shortcuts only on the dashboard and room board
        $this->get('/reservations')->assertDontSee('id="quickbar"', false);
        $this->get('/rooms')->assertSee('id="quickbar"', false);
        $this->get('/')->assertSee('id="quickbar"', false);
        $this->get('/breakfast?date='.today()->addDay()->toDateString())->assertOk()
            ->assertSee('Abdi')->assertSee('Hodan')->assertSee('Expected')->assertSee('expected tonight');

        // Next morning: both guests had breakfast (Hodan checks out after it); the no-show is not listed
        $this->travel(1)->days();
        $this->post("/reservations/{$leaver->id}/payments", ['amount' => $leaver->fresh()->balance(), 'method' => 'cash', 'type' => 'payment']);
        $this->post("/reservations/{$leaver->id}/check-out")->assertSessionHas('success');
        $res = $this->get('/breakfast')->assertOk()->assertSee('Abdi')->assertSee('Hodan')->assertDontSee('Expected');
        $this->assertEquals(4, $res->viewData('adults') + $res->viewData('children')); // 2 adults + 1 child + 1 adult

        // A group booking is listed room by room, with its guests shared out over the rooms
        $this->travel(-2)->days();
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(3)->toDateString(),
            'rooms' => Room::whereIn('number', ['201', '202', '203'])->pluck('id')->all(), 'adults' => 5, 'source' => 'corporate',
            'guest' => ['first_name' => 'Amina', 'phone' => '0911555666'],
        ])->assertSessionHasNoErrors();
        $group = Reservation::latest('id')->first();
        $this->post("/reservations/{$group->id}/check-in", $this->checkInPayload($group))->assertSessionHas('success');
        $this->travel(2)->days();
        $rows = $this->get('/breakfast')->viewData('rows')->where('r.id', $group->id)->values();
        $this->assertEquals(['201', '202', '203'], $rows->pluck('room')->all());
        $this->assertEquals(['Amina', 'Guest202', 'Guest203'], $rows->pluck('name')->map(fn ($n) => explode(' ', $n)[0])->all());
        $this->assertEquals([2, 2, 1], $rows->pluck('adults')->all());

        // The morning after: only Abdi
        $this->travel(1)->days();
        $this->get('/breakfast')->assertSee('Abdi')->assertDontSee('Hodan');
    }
}
