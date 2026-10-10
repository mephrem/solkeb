<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_revenue_occupancy_and_night_audit(): void
    {
        Cache::flush();
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(10, 0));
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $rec = User::create(['name' => 'Hodan', 'email' => 'h@x.com', 'password' => 'secret123', 'role' => 'receptionist']);

        // Stay 1: Deluxe 2500 x 3 nights, 18th-21st (checked out on 21st)
        $g = Guest::create(['first_name' => 'Abdi', 'phone' => '0911000011']);
        $r1 = Reservation::create(['guest_id' => $g->id, 'check_in_date' => '2026-09-18', 'check_out_date' => '2026-09-21',
            'status' => 'checked_out', 'checked_in_at' => '2026-09-18 14:00', 'checked_out_at' => '2026-09-21 11:00']);
        $r1->rooms()->attach(Room::where('number', '301')->value('id'), ['nightly_rate' => 2500]);
        $r1->charges()->create(['type' => 'service', 'service_id' => 1, 'description' => 'Breakfast', 'quantity' => 2, 'unit_price' => 300, 'amount' => 600, 'charge_date' => '2026-09-19']);
        $r1->charges()->create(['type' => 'discount', 'description' => 'Loyalty', 'quantity' => 1, 'unit_price' => -100, 'amount' => -100, 'charge_date' => '2026-09-20']);
        $r1->payments()->create(['amount' => 5000, 'method' => 'telebirr', 'reference' => 'T1', 'type' => 'payment', 'paid_at' => '2026-09-20 09:00', 'received_by' => $rec->id]);
        $r1->payments()->create(['amount' => 3000, 'method' => 'cash', 'type' => 'payment', 'paid_at' => '2026-09-20 09:30', 'received_by' => $admin->id]);
        $r1->payments()->create(['amount' => 500, 'method' => 'cash', 'type' => 'refund', 'paid_at' => '2026-09-20 09:40', 'received_by' => $admin->id]);

        // Stay 2: in house, Standard 1500, 19th -> 23rd; only nights up to today (20th) count
        $g2 = Guest::create(['first_name' => 'Hodan', 'phone' => '0922000022']);
        $r2 = Reservation::create(['guest_id' => $g2->id, 'check_in_date' => '2026-09-19', 'check_out_date' => '2026-09-23',
            'status' => 'checked_in', 'checked_in_at' => '2026-09-19 15:00']);
        $r2->rooms()->attach(Room::where('number', '101')->value('id'), ['nightly_rate' => 1500]);
        $r2->charges()->create(['type' => 'room', 'room_id' => 1, 'description' => 'Room 101', 'quantity' => 4, 'unit_price' => 1500, 'amount' => 6000, 'charge_date' => '2026-09-19']);

        // Confirmed (not arrived) booking must not count
        $r3 = Reservation::create(['guest_id' => $g2->id, 'check_in_date' => '2026-09-18', 'check_out_date' => '2026-09-20', 'status' => 'confirmed']);
        $r3->rooms()->attach(Room::where('number', '102')->value('id'), ['nightly_rate' => 1500]);

        $this->actingAs($admin);
        $res = $this->get('/reports?from=2026-09-18&to=2026-09-20')->assertOk();
        $d = $res->viewData('days');
        $t = $res->viewData('totals');

        $this->assertEquals([1, 2, 2], array_column($d, 'sold'));              // 18th: r1; 19th, 20th: r1 + r2
        $this->assertEquals([2500, 4000, 4000], array_column($d, 'room'));
        $this->assertEquals([0, 600, -100], array_column($d, 'other'));
        $this->assertEquals(10500, $t['room']);
        $this->assertEquals(11000, $t['revenue']);
        $this->assertEquals(7500, $t['collected']);                            // 5000 + 3000 - 500
        $this->assertEquals(5, $t['sold']);
        $this->assertEquals(26 * 3, $t['available']);
        $this->assertEquals(2100, $t['adr']);                                  // 10500 / 5
        $this->assertEquals(6000, $res->viewData('owed'));                     // r2 unpaid
        $this->assertEquals(['telebirr' => 5000, 'cash' => 2500], $res->viewData('byMethod'));
        $this->assertEquals(['Rooms' => 10500.0, 'Restaurant' => 600.0, 'Discounts' => -100.0], $res->viewData('byCategory'));

        // Future part of a range doesn't count as sold
        $f = $this->get('/reports?from=2026-09-20&to=2026-09-25')->viewData('days');
        $this->assertEquals([2, 0, 0, 0, 0, 0], array_column($f, 'sold'));

        // Night audit for the 20th: cash-up per staff member
        $audit = $this->get('/reports/daily?date=2026-09-20')->assertOk()->assertSee('Cash-up by staff member');
        $by = $audit->viewData('byCashier');
        $this->assertEquals(5000, $by['Hodan']['telebirr']);
        $this->assertEquals(2500, $by['Administrator']['cash']);
        $this->assertEquals(7500, $audit->viewData('collected'));
        $this->assertEquals(2, $audit->viewData('roomsSold'));

        // CSV
        $csv = $this->get('/reports/export?from=2026-09-18&to=2026-09-20')->assertOk()->streamedContent();
        $this->assertStringContainsString('2026-09-19,2,26,8,4000.00,600.00,4600.00,0.00', $csv);
        $this->assertStringContainsString('TOTAL,5,78,6,10500.00,500.00,11000.00,7500.00', $csv);

        // Who can see reports
        $this->actingAs($rec)->get('/reports')->assertForbidden();
        $acc = User::create(['name' => 'Acc', 'email' => 'a@x.com', 'password' => 'secret123', 'role' => 'accountant']);
        $this->actingAs($acc)->get('/reports')->assertOk();
        $this->actingAs($acc)->get('/reports/daily')->assertOk();
    }
}
