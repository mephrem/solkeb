<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\SampleBookingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** What the owner asked for on 2 Oct: groups, reserved rooms, ID photos, staff tracking, no housekeeping. */
class OwnerRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('take_deposits', '1'); // these tests take deposits at booking
    }

    private function staff(string $name, string $role = 'receptionist'): User
    {
        return User::create(['name' => $name, 'email' => strtolower($name).'@x.com', 'password' => 'secret123', 'role' => $role]);
    }

    private function book(array $rooms, string $phone, array $guest = [], int $in = 0, int $out = 2, array $extra = []): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->addDays($in)->toDateString(), 'check_out_date' => today()->addDays($out)->toDateString(),
            'rooms' => Room::whereIn('number', $rooms)->pluck('id')->all(), 'adults' => count($rooms), 'source' => 'corporate',
            'guest' => $guest + ['first_name' => 'Contact', 'phone' => $phone],
        ] + $extra)->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_group_booking_colours_and_reserved_rooms_on_the_board(): void
    {
        Cache::flush();
        $this->actingAs($this->staff('Hodan'));

        $unicef = $this->book(['201', '202', '203'], '0911', ['first_name' => 'Amina', 'company' => 'UNICEF Jijiga']);
        $unhcr = $this->book(['204', '205'], '0922', ['first_name' => 'Omar', 'company' => 'UNHCR']);
        $single = $this->book(['101'], '0933', ['first_name' => 'Sara']);
        $later = $this->book(['102'], '0944', ['first_name' => 'Dawit'], 3, 5);

        $this->assertNotNull($unicef->fresh()->group_color);
        $this->assertNotNull($unhcr->fresh()->group_color);
        $this->assertNotEquals($unicef->fresh()->group_color, $unhcr->fresh()->group_color);
        $this->assertNull($single->fresh()->group_color);

        // Removing rooms down to one drops the colour
        $this->put("/reservations/{$unhcr->id}", ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => [Room::where('number', '204')->value('id')], 'adults' => 1, 'source' => 'corporate']);
        $this->assertNull($unhcr->fresh()->group_color);

        // Room board: arriving guests show as Reserved, group rooms carry the organisation and its colour
        $board = $this->get('/rooms')->assertOk()->assertSee('Group bookings')->assertSee('UNICEF Jijiga · 3 rooms · arriving');
        $rows = collect($board->viewData('floors'))->flatten(1)->keyBy(fn ($b) => $b->room->number);
        $this->assertEquals('reserved', $rows['201']->state);
        $this->assertEquals($unicef->fresh()->group_color, $rows['203']->color);
        $this->assertEquals('reserved', $rows['101']->state);
        $this->assertNull($rows['101']->color);
        $this->assertEquals('available', $rows['102']->state);               // booked only from day 3
        $this->assertEquals($later->id, $rows['102']->next->id);
        $board->assertSee('Arrives today')->assertSee('Next: '.today()->addDays(3)->format('d M'));
        $this->assertEquals(5, $board->viewData('counts')['reserved']); // 201-203, 204, 101
    }

    public function test_check_in_takes_each_rooms_guest_and_id_photos(): void
    {
        Cache::flush();
        $this->actingAs($this->staff('Hodan'));
        $r = $this->book(['301', '302'], '0911', ['first_name' => 'Amina', 'company' => 'Save the Children']);
        [$r301, $r302] = [Room::where('number', '301')->value('id'), Room::where('number', '302')->value('id')];

        $this->get("/reservations/{$r->id}")->assertSee('Room 301 · Deluxe')->assertSee('Room 302 · Deluxe')->assertSee('ID front (photo)');

        // No photos -> one clear error per missing photo
        $payload = $this->checkInPayload($r);
        unset($payload['occupants'][$r301]['id_front'], $payload['occupants'][$r302]['id_back']);
        $this->post("/reservations/{$r->id}/check-in", $payload)
            ->assertSessionHasErrors(["occupants.{$r301}.id_front", "occupants.{$r302}.id_back"]);
        $this->assertEquals('confirmed', $r->fresh()->status);

        // A wrong file type is refused
        $bad = $this->checkInPayload($r);
        $bad['occupants'][$r301]['id_front'] = UploadedFile::fake()->create('virus.exe', 10);
        $this->post("/reservations/{$r->id}/check-in", $bad)->assertSessionHasErrors("occupants.{$r301}.id_front");

        // Each room gets its own guest; the contact stays the booker and the bill's name
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r, [
            '301' => ['first_name' => 'Yusuf', 'father_name' => 'Ali', 'phone' => '0955', 'id_number' => 'FAN-1'],
            '302' => ['first_name' => 'Hawa', 'phone' => '0966', 'id_type' => 'passport', 'id_number' => 'EP123'],
        ]))->assertSessionHas('success');
        $r->refresh();
        $this->assertEquals('checked_in', $r->status);
        $this->assertEquals('Amina', $r->guest->first_name);
        $yusuf = Guest::where('id_number', 'FAN-1')->first();
        $hawa = Guest::where('id_number', 'EP123')->first();
        $this->assertEquals($yusuf->id, $r->rooms()->where('rooms.id', $r301)->first()->pivot->guest_id);
        $this->assertEquals($hawa->id, $r->rooms()->where('rooms.id', $r302)->first()->pivot->guest_id);
        Storage::disk('local')->assertExists([$yusuf->id_front_path, $yusuf->id_back_path, $hawa->id_front_path]);
        $this->assertStringStartsWith("ids/{$yusuf->id}/", $yusuf->id_front_path);

        // Booking page and board show who is in which room; guest profile lists the stay
        $this->get("/reservations/{$r->id}")->assertSee('Yusuf Ali')->assertSee('Hawa')->assertSee('Booking contact');
        $this->get('/rooms')->assertSee('Yusuf Ali');
        $this->get("/guests/{$yusuf->id}")->assertSee($r->code);
        $this->delete("/guests/{$yusuf->id}")->assertSessionHas('error');   // has a stay -> kept

        // ID photos: visible to staff only, never from the public web folder
        $this->get("/guests/{$yusuf->id}/id/front")->assertOk();
        $this->get("/guests/{$yusuf->id}/id/sideways")->assertNotFound();
        auth()->logout();
        $this->get("/guests/{$yusuf->id}/id/front")->assertRedirect('/login');
        $this->assertFileDoesNotExist(public_path($yusuf->id_front_path));

        // Returning guest: ID photos already saved, so none needed this time; passport needs no back photo
        $this->actingAs(User::where('name', 'Hodan')->first());
        $again = $this->book(['303'], '0955', ['first_name' => 'Yusuf'], 3, 4);
        $again->update(['check_in_date' => today(), 'check_out_date' => today()->addDay()]);
        $r303 = Room::where('number', '303')->value('id');
        $this->post("/reservations/{$again->id}/check-in", ['occupants' => [$r303 => [
            'first_name' => 'Yusuf', 'phone' => '0955', 'id_type' => 'national_id', 'id_number' => 'FAN-1',
        ]]])->assertSessionHasNoErrors()->assertSessionHas('success');
        $this->assertEquals(1, Guest::where('id_number', 'FAN-1')->count());
    }

    public function test_who_reserved_checked_in_received_payment_and_checked_out(): void
    {
        Cache::flush();
        [$hodan, $sara, $abdi, $liya] = [$this->staff('Hodan'), $this->staff('Sara'), $this->staff('Abdi'), $this->staff('Liya')];
        $acc = $this->staff('Meron', 'accountant');

        $this->actingAs($hodan);
        $r = $this->book(['101'], '0911', ['first_name' => 'Ahmed'], 0, 2, ['deposit' => 1000, 'deposit_method' => 'cash']);
        $this->actingAs($sara)->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        $this->actingAs($abdi)->post("/reservations/{$r->id}/payments", ['amount' => 1500, 'method' => 'telebirr', 'type' => 'payment', 'reference' => 'TB1']);
        $this->travel(2)->days(); // a different receptionist, two days later
        $this->actingAs($liya)->post("/reservations/{$r->id}/payments", ['amount' => 500, 'method' => 'cash', 'type' => 'payment']);
        $this->actingAs($liya)->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');

        $r->refresh();
        $this->assertEquals([$hodan->id, $sara->id, $liya->id], [$r->created_by, $r->checked_in_by, $r->checked_out_by]);
        $this->assertEquals(['reserved', 'deposit', 'checked_in', 'payment', 'payment', 'checked_out'],
            Activity::where('reservation_id', $r->id)->orderBy('id')->pluck('action')->all());
        $this->assertEquals([$hodan->id, $hodan->id, $sara->id, $abdi->id, $liya->id, $liya->id],
            Activity::where('reservation_id', $r->id)->orderBy('id')->pluck('user_id')->all());

        // Booking page: "Who did what" and the history
        $page = $this->actingAs($acc)->get("/reservations/{$r->id}")->assertOk()->assertSee('Who did what')->assertSee('History');
        foreach (['Hodan', 'Sara', 'Abdi', 'Liya'] as $n) {
            $page->assertSee($n);
        }
        // Reservations list: the Excel-style columns
        $this->get('/reservations')->assertSee('Paid to')->assertSee('Checked out')->assertSee('Hodan, Abdi, Liya');

        // Staff report for the whole stay
        $rep = $this->get('/reports/staff?from='.today()->subDays(2)->toDateString().'&to='.today()->toDateString())->assertOk();
        $rows = collect($rep->viewData('rows'))->keyBy(fn ($x) => $x->user->name);
        $this->assertEquals([1, 0, 0, 1000.0], [$rows['Hodan']->reserved, $rows['Hodan']->checked_in, $rows['Hodan']->checked_out, $rows['Hodan']->total]);
        $this->assertEquals(1, $rows['Sara']->checked_in);
        $this->assertEquals(1500.0, $rows['Abdi']->by_method['telebirr']);
        $this->assertEquals([1, 500.0], [$rows['Liya']->checked_out, $rows['Liya']->by_method['cash']]);
        $this->assertEquals(3000.0, collect($rows)->sum('total'));
        // Today only: just Liya's payment and check-out
        $todayRows = collect($this->get('/reports/staff')->viewData('rows'))->keyBy(fn ($x) => $x->user->name);
        $this->assertEquals(['Liya'], $todayRows->keys()->all());

        // Receptionists cannot see the staff report, give refunds or discounts (even by sending the form by hand)
        $this->actingAs($sara)->get('/reports/staff')->assertForbidden();
        $r2 = $this->actingAs($sara)->book(['102'], '0922', ['first_name' => 'Ali']);
        $this->actingAs($sara)->post("/reservations/{$r2->id}/payments", ['amount' => 100, 'method' => 'cash', 'type' => 'refund'])->assertForbidden();
        $this->actingAs($sara)->post("/reservations/{$r2->id}/charges", ['kind' => 'discount', 'description' => 'x', 'amount' => 100])->assertForbidden();
        $this->actingAs($acc)->post("/reservations/{$r2->id}/payments", ['amount' => 100, 'method' => 'cash', 'type' => 'refund'])->assertSessionHasNoErrors()->assertSessionHas('success');

        // Cancelling records who did it
        $this->actingAs($sara)->post("/reservations/{$r2->id}/cancel");
        $this->assertEquals($sara->id, $r2->fresh()->cancelled_by);
    }

    public function test_sample_bookings_seeder(): void
    {
        Cache::flush();
        $this->seed(SampleBookingsSeeder::class);
        $in = Reservation::where('status', 'checked_in')->get();
        $this->assertCount(10, $in);
        $this->assertEquals([today()->subDays(3)->toDateString()], $in->map(fn ($r) => $r->check_in_date->toDateString())->unique()->values()->all());
        $this->assertEquals(4, $in->filter(fn ($r) => $r->check_out_date->isToday())->count());
        $this->assertEquals(6, $in->filter(fn ($r) => $r->check_out_date->eq(today()->addDays(2)))->count());
        $this->assertEquals(20, DB::table('reservation_room')->join('reservations', 'reservations.id', '=', 'reservation_id')->where('status', 'confirmed')->count());
        $this->assertEquals([3, 2], Reservation::whereNotNull('group_color')->withCount('rooms')->orderByDesc('rooms_count')->pluck('rooms_count')->all());
        // No room is double-booked
        foreach (Reservation::whereIn('status', ['confirmed', 'checked_in'])->with('rooms')->get() as $r) {
            foreach ($r->rooms as $room) {
                $this->assertEquals(0, $room->reservations()->whereIn('status', ['confirmed', 'checked_in'])->where('reservations.id', '!=', $r->id)
                    ->where('check_in_date', '<', $r->check_out_date)->where('check_out_date', '>', $r->check_in_date)->count(), "Room {$room->number} double-booked");
            }
        }
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $this->get('/rooms')->assertOk()->assertSee('Next: today');
        $this->get('/reports/staff?from='.today()->subDays(6)->toDateString())->assertOk()->assertSee('Liya Bekele');
    }

    public function test_housekeeping_is_gone_and_reception_marks_rooms_clean(): void
    {
        Cache::flush();
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $this->actingAs($admin);
        $this->get('/housekeeping')->assertNotFound();
        $this->get('/')->assertDontSee('Housekeeping');
        $this->get('/setup/users/create')->assertDontSee('>Housekeeping<', false);
        $this->post('/setup/users', ['name' => 'X', 'email' => 'x@x.com', 'role' => 'housekeeping', 'password' => 'secret123', 'password_confirmation' => 'secret123'])
            ->assertSessionHasErrors('role');

        $room = Room::where('number', '105')->first();
        $room->update(['housekeeping_status' => 'dirty']);
        $this->get('/rooms')->assertSee('Needs cleaning')->assertSee('Mark clean');
        $rec = $this->staff('Hodan');
        $this->actingAs($rec)->patch("/rooms/{$room->id}", ['housekeeping_status' => 'clean'])->assertSessionHas('success', 'Room 105 marked clean.');
        $this->assertEquals('clean', $room->fresh()->housekeeping_status);
        $acc = $this->staff('Meron', 'accountant');
        $this->actingAs($acc)->patch("/rooms/{$room->id}", ['housekeeping_status' => 'dirty'])->assertForbidden();
    }
}
