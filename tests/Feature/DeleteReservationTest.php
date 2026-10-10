<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DeleteReservationTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('take_deposits', '1'); // these tests take deposits at booking
    }

    private function book(string $room, int $in, int $out, array $extra = []): Reservation
    {
        $this->post('/reservations', ['check_in_date' => today()->addDays($in)->toDateString(), 'check_out_date' => today()->addDays($out)->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'phone',
            'guest' => ['first_name' => 'Abdi', 'phone' => '0911000011']] + $extra)->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_manager_deletes_a_mistaken_booking(): void
    {
        Cache::flush();
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $mgr = User::create(['name' => 'Manager Mo', 'email' => 'm@x.com', 'password' => 'secret123', 'role' => 'manager']);
        $rec = User::create(['name' => 'Rec', 'email' => 'r@x.com', 'password' => 'secret123', 'role' => 'receptionist']);
        $room = Room::where('number', '101')->first();

        // Receptionist makes a duplicate booking with a mistyped deposit
        $this->actingAs($rec);
        $real = $this->book('101', 0, 2, ['deposit' => 1500, 'deposit_method' => 'cash']);
        $dup = $this->book('102', 0, 2, ['deposit' => 15000, 'deposit_method' => 'cash']);
        $this->delete("/reservations/{$dup->id}", ['reason' => 'Duplicate'])->assertForbidden();

        // Manager: reason required
        $this->actingAs($mgr);
        $this->get("/reservations/{$dup->id}")->assertSee('Delete booking')->assertSee('15,000.00 ETB) will also be removed', false);
        $this->delete("/reservations/{$dup->id}", ['reason' => ''])->assertSessionHasErrors('reason');
        $this->assertNotSoftDeleted($dup);

        $this->delete("/reservations/{$dup->id}", ['reason' => 'Duplicate of the booking for room 101'])->assertRedirect('/reservations');
        $this->assertSoftDeleted($dup);
        $dup = Reservation::withTrashed()->find($dup->id);
        $this->assertEquals($mgr->id, $dup->deleted_by);

        // Gone from lists, detail page, dashboard money, reports and the cash-up
        $this->get('/reservations')->assertSee('deleted. It is kept in the Deleted bookings log'); // the success message
        $this->get('/reservations')->assertDontSee($dup->code)->assertSee($real->code);
        $this->get("/reservations/{$dup->id}")->assertNotFound();
        $this->assertEquals(1500, Payment::sum('amount'));
        $this->get('/')->assertSee('1,500')->assertDontSee('16,500');
        $this->actingAs($admin);
        $this->assertEquals(1500, $this->get('/reports?from='.today()->toDateString().'&to='.today()->toDateString())->viewData('totals')['collected']);
        $audit = $this->get('/reports/daily')->assertSee('Deleted bookings')->assertSee('Duplicate of the booking for room 101');
        $this->assertEquals(1500, $audit->viewData('collected'));

        // Room 102 is free again for the same dates
        $this->assertTrue(Room::availableBetween(today()->toDateString(), today()->addDays(2)->toDateString())->where('number', '102')->exists());

        // New bookings never reuse the deleted booking's code
        $next = $this->book('103', 0, 1);
        $this->assertNotEquals($dup->code, $next->code);

        // The log shows it, with the money, who and why
        $this->get('/reservations/deleted')->assertOk()->assertSee($dup->code)->assertSee('Manager Mo')->assertSee('15,000.00');

        // History guards still see it: guest, room and staff can't be deleted out from under the log
        $lone = Guest::create(['first_name' => 'Solo', 'phone' => '0988000088']);
        $this->actingAs($rec);
        $solo = $this->book('104', 0, 1);
        $solo->update(['guest_id' => $lone->id]);
        $this->actingAs($mgr)->delete("/reservations/{$solo->id}", ['reason' => 'Test entry']);
        $this->actingAs($admin)->delete("/guests/{$lone->id}")->assertSessionHas('error');
        $this->delete('/setup/rooms/'.Room::where('number', '104')->value('id'))->assertSessionHas('error');
        $this->assertTrue($mgr->fresh()->hasHistory());

        // Admin restores it; payments come back
        $this->post("/reservations/deleted/{$dup->id}/restore")->assertRedirect("/reservations/{$dup->id}");
        $this->assertEquals(16500, Payment::sum('amount'));
        $this->actingAs($mgr)->post("/reservations/deleted/{$solo->id}/restore")->assertForbidden();
    }

    public function test_checked_in_and_finished_stays(): void
    {
        Cache::flush();
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $mgr = User::create(['name' => 'Mgr', 'email' => 'm@x.com', 'password' => 'secret123', 'role' => 'manager']);
        $this->actingAs($admin);

        // Wrong guest checked in: deleting frees the room
        $r = $this->book('201', 0, 2);
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r));
        $room = Room::where('number', '201')->first();
        $this->assertEquals('occupied', $room->fresh()->status);
        $this->actingAs($mgr)->delete("/reservations/{$r->id}", ['reason' => 'Checked in the wrong booking']);
        $this->assertEquals('available', $room->fresh()->status);
        $this->get('/rooms')->assertOk();

        // Can't restore once the room is taken again
        $this->book('201', 0, 1);
        $this->actingAs($admin)->post("/reservations/deleted/{$r->id}/restore")->assertSessionHas('error');
        $this->assertSoftDeleted($r);

        // Finished stay: manager blocked, admin allowed
        $done = $this->book('202', 0, 1, ['deposit' => 1500, 'deposit_method' => 'cash']);
        $this->post("/reservations/{$done->id}/check-in", $this->checkInPayload($done));
        $this->post("/reservations/{$done->id}/check-out");
        $this->assertEquals('checked_out', $done->fresh()->status);
        $this->actingAs($mgr)->delete("/reservations/{$done->id}", ['reason' => 'x x x'])->assertSessionHas('error');
        $this->assertNotSoftDeleted($done);
        $this->actingAs($admin)->delete("/reservations/{$done->id}", ['reason' => 'Test stay'])->assertRedirect('/reservations');
        $this->assertSoftDeleted($done);
        $this->assertEquals(0, $this->get('/reports?from='.today()->toDateString().'&to='.today()->toDateString())->viewData('totals')['room']);
    }
}
