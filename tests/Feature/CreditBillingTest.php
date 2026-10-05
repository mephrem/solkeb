<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreditBillingTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@solkebhotel.com')->first();
    }

    private function checkIn(Reservation $r): void
    {
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
    }

    public function test_managers_manage_organisations_and_credit(): void
    {
        $rec = User::create(['name' => 'Sara', 'email' => 'sara@x.com', 'password' => 'password', 'role' => 'receptionist', 'is_active' => true]);
        $this->actingAs($rec)->get('/organizations')->assertOk();
        $this->get('/organizations/create')->assertForbidden();
        $this->post('/organizations', ['name' => 'X', 'credit_days' => 30, 'credit_allowed' => 1])->assertForbidden();

        $this->actingAs($this->admin())->post('/organizations', [
            'name' => 'Education Office', 'contact_person' => 'Ahmed Basha', 'contact_title' => 'Finance Head',
            'phone' => '0915553655', 'credit_allowed' => 1, 'credit_days' => 15, 'is_active' => 1,
        ])->assertRedirect();
        $org = Organization::first();
        $this->assertTrue($org->credit_allowed);
        $this->get("/organizations/{$org->id}")->assertOk()->assertSee('Credit · 15 days')->assertSee('Nothing owed');
    }

    public function test_credit_booking_checks_out_unpaid_and_is_billed_to_the_organisation(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Education Office', 'contact_person' => 'Ahmed', 'credit_allowed' => true, 'credit_days' => 15]);
        $cash = Organization::create(['name' => 'Walk-in NGO', 'credit_allowed' => false]);
        $acc = PaymentAccount::create(['kind' => 'bank', 'provider' => 'CBE', 'account_name' => 'Solkeb Hotel', 'account_number' => '1000503354938']);
        $rooms = Room::whereIn('number', ['201', '202'])->pluck('id')->all();
        $base = ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => $rooms, 'adults' => 2, 'source' => 'corporate', 'guest' => ['first_name' => 'Ahmed', 'phone' => '0915553655']];

        $this->actingAs($admin)->get('/reservations/create')->assertSee('Bill to')->assertSee('Education Office · credit allowed');
        // Credit only for organisations a manager allowed it
        $this->post('/reservations', $base + ['organization_id' => $cash->id, 'on_credit' => 1])->assertSessionHasErrors('on_credit');
        $this->post('/reservations', $base + ['on_credit' => 1])->assertSessionHasErrors('on_credit');
        $this->post('/reservations', $base + ['organization_id' => $org->id, 'on_credit' => 1])->assertSessionHasNoErrors();

        $r = Reservation::first();
        $this->assertTrue($r->on_credit);
        $this->assertEquals($org->id, $r->organization_id);
        $this->assertEquals('Education Office', $r->guest->fresh()->company);

        $this->checkIn($r);
        $owed = $r->fresh()->balance();
        $this->assertGreaterThan(0, $owed);

        // A guest who pays themselves still can't leave with a balance; the credit booking can
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');
        $r->refresh();
        $this->assertEquals('checked_out', $r->status);
        $this->assertEquals('CI-'.now()->format('Y').'-0001', $r->credit_invoice_no);
        $this->assertEquals(today()->addDays(15)->toDateString(), $r->creditDueDate()->toDateString());
        $this->assertStringContainsString('on credit', $r->activities()->where('action', 'checked_out')->first()->details);
        $this->assertEquals(0, Room::whereIn('id', $rooms)->where('status', 'occupied')->count());

        // Booking page, organisation statement, credit invoice and letter
        $this->get("/reservations/{$r->id}")->assertSee('owes')->assertSee('Payment request letter');
        $this->get("/organizations/{$org->id}")->assertSee($r->credit_invoice_no)->assertSee(number_format($owed, 2));
        $this->get('/organizations')->assertSee(number_format($owed, 2));
        $this->get("/reservations/{$r->id}/credit-invoice")->assertOk()->assertSee('Credit invoice')->assertSee('Bill to')
            ->assertSee('Education Office')->assertSee('1000503354938')->assertSee('Payment due: '.today()->addDays(15)->format('d M Y'));
        $this->get("/organizations/{$org->id}/payment-request")->assertOk()->assertSee('Request for payment')
            ->assertSee($r->credit_invoice_no)->assertSee(number_format($owed, 2))->assertSee('Attn: Ahmed');
        // Each room's own guest is named on the letter, as on the invoice (checkInPayload: Ahmed in 201, Guest202 in 202)
        $this->get("/organizations/{$org->id}/payment-request")->assertSee('Guest202');
        $this->get("/reservations/{$r->id}/credit-invoice")->assertSee('Guest202');

        // Only one account is printed: the first bank account by default, or the one the admin ticks
        $ebirr = PaymentAccount::create(['kind' => 'wallet', 'provider' => 'E-birr', 'account_name' => 'Abdi', 'account_number' => '0915000000']);
        $coop = PaymentAccount::create(['kind' => 'bank', 'provider' => 'Coop Bank', 'account_name' => 'Solkeb', 'account_number' => '1022000111']);
        foreach (["/reservations/{$r->id}/credit-invoice", "/organizations/{$org->id}/payment-request"] as $doc) {
            $this->get($doc)->assertSee('1000503354938')->assertDontSee('1022000111')->assertDontSee('0915000000');
        }
        $this->put("/setup/accounts/{$coop->id}", ['kind' => 'bank', 'provider' => 'Coop Bank', 'account_name' => 'Solkeb',
            'account_number' => '1022000111', 'is_active' => 1, 'on_invoices' => 1])->assertSessionHasNoErrors();
        foreach (["/reservations/{$r->id}/credit-invoice", "/organizations/{$org->id}/payment-request"] as $doc) {
            $this->get($doc)->assertSee('1022000111')->assertDontSee('1000503354938')->assertDontSee('0915000000');
        }
        $this->get('/setup/accounts')->assertSee('On invoices');

        // The organisation pays later into the bank account: nothing owed any more
        $this->post("/reservations/{$r->id}/payments", ['amount' => $owed, 'received_into' => $acc->id, 'type' => 'payment', 'reference' => 'FT1', 'payer_name' => 'Education Office'])
            ->assertSessionHas('success');
        $this->assertEquals(0.0, $r->fresh()->balance());
        $this->get("/organizations/{$org->id}")->assertSee('Nothing owed');
        $this->get("/organizations/{$org->id}/payment-request")->assertSee('no unpaid credit invoices');
    }

    public function test_guests_due_out_today_or_overdue_are_red(): void
    {
        $admin = $this->admin();
        $room = Room::where('number', '101')->first();
        $this->actingAs($admin)->post('/reservations', ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDays(2)->toDateString(),
            'rooms' => [$room->id], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'Late', 'phone' => '0911000999']]);
        $r = Reservation::first();
        $this->checkIn($r);

        $this->get("/reservations/{$r->id}/credit-invoice")->assertNotFound(); // not a credit booking
        $this->get('/rooms')->assertDontSee('rm-due');
        $this->assertNull($r->fresh()->departureState());

        // Due out today
        $this->travel(2)->days();
        $this->assertEquals('today', $r->fresh()->departureState());
        $this->get('/rooms')->assertSee('rm-due', false)->assertSee('Leaves today');
        $this->get('/')->assertSee('row-due', false)->assertSee('Leaves today');
        $this->get('/reservations')->assertSee('row-due', false);

        // Still here the day after: overdue, still listed under departures
        $this->travel(1)->days();
        $this->assertEquals('overdue', $r->fresh()->departureState());
        $this->get('/rooms')->assertSee('rm-due', false)->assertSee('Overdue 1 day');
        $this->get('/')->assertSee('Overdue 1 day');
        $this->get("/reservations/{$r->id}")->assertSee('Overdue 1 day');

        // The overdue guest is still in the room: it is not offered for new bookings from today, only later
        $this->assertFalse(Room::availableBetween(today()->toDateString(), today()->addDay()->toDateString())->whereKey($room->id)->exists());
        $this->assertTrue(Room::availableBetween(today()->addDay()->toDateString(), today()->addDays(2)->toDateString())->whereKey($room->id)->exists());
    }
}
