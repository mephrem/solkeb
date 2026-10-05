<?php

namespace Tests\Feature;

use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentAccountTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('take_deposits', '1'); // these tests take deposits at booking
    }

    private function admin(): User
    {
        return User::where('email', 'admin@solkebhotel.com')->first();
    }

    private function booking(User $by, array $extra = []): Reservation
    {
        $room = Room::where('number', '101')->first();
        $this->actingAs($by)->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [$room->id], 'adults' => 1, 'source' => 'phone',
            'guest' => ['first_name' => 'Hodan', 'phone' => '0911222333'],
        ] + $extra)->assertSessionHasNoErrors();

        return Reservation::latest('id')->first();
    }

    public function test_only_admin_manages_accounts(): void
    {
        $rec = User::create(['name' => 'Sara', 'email' => 'sara@x.com', 'password' => 'password', 'role' => 'receptionist', 'is_active' => true]);
        $manager = User::create(['name' => 'Mo', 'email' => 'mo@x.com', 'password' => 'password', 'role' => 'manager', 'is_active' => true]);
        $this->actingAs($rec)->get('/setup/accounts')->assertForbidden();
        $this->actingAs($manager)->get('/setup/accounts')->assertForbidden();

        $admin = $this->admin();
        $this->actingAs($admin)->get('/setup/accounts')->assertOk()->assertSee('No accounts yet');
        $this->post('/setup/accounts', ['kind' => 'bank', 'provider' => 'Commercial Bank of Ethiopia (CBE)', 'account_name' => 'Solkeb Hotel', 'account_number' => '1000503354938', 'is_active' => 1])
            ->assertRedirect('/setup/accounts');
        // A second account at the same bank is fine; the same number twice is not
        $this->post('/setup/accounts', ['kind' => 'bank', 'provider' => 'Commercial Bank of Ethiopia (CBE)', 'account_name' => 'Owner', 'account_number' => '1000339848638', 'is_active' => 1])
            ->assertSessionHasNoErrors();
        $this->post('/setup/accounts', ['kind' => 'bank', 'provider' => 'Commercial Bank of Ethiopia (CBE)', 'account_name' => 'Dup', 'account_number' => '1000503354938'])
            ->assertSessionHasErrors('account_number');
        $this->assertEquals(2, PaymentAccount::count());
        $this->get('/setup/accounts')->assertSee('1000339848638')->assertSee('Owner');
    }

    public function test_payment_records_the_account_it_went_into(): void
    {
        $admin = $this->admin();
        $r = $this->booking($admin);
        $cbe = PaymentAccount::create(['kind' => 'bank', 'provider' => 'CBE', 'account_name' => 'Solkeb Hotel', 'account_number' => '1000503354938']);
        $ebirr = PaymentAccount::create(['kind' => 'wallet', 'provider' => 'E-birr', 'account_name' => 'Abdi N.', 'account_number' => '0915000000']);
        $old = PaymentAccount::create(['kind' => 'bank', 'provider' => 'Awash', 'account_name' => 'Closed', 'account_number' => '999', 'is_active' => false]);

        // The payment form now asks where the money went
        $this->get("/reservations/{$r->id}")->assertSee('Received into')->assertSee('E-birr · 0915000000 · Abdi N.')->assertDontSee('Awash · 999');

        // Once accounts exist, a bank/mobile payment must name its account
        $this->post("/reservations/{$r->id}/payments", ['amount' => 100, 'method' => 'telebirr', 'type' => 'payment', 'reference' => 'X'])
            ->assertSessionHasErrors('received_into');
        // Inactive accounts can't be used
        $this->post("/reservations/{$r->id}/payments", ['amount' => 100, 'received_into' => $old->id, 'type' => 'payment', 'reference' => 'X'])
            ->assertSessionHasErrors('received_into');
        // Transaction number still required
        $this->post("/reservations/{$r->id}/payments", ['amount' => 100, 'received_into' => $ebirr->id, 'type' => 'payment'])
            ->assertSessionHasErrors('reference');

        $this->post("/reservations/{$r->id}/payments", ['amount' => 600, 'received_into' => $ebirr->id, 'type' => 'payment', 'reference' => '2600563350', 'payer_name' => 'Jafer'])
            ->assertSessionHas('success');
        $this->post("/reservations/{$r->id}/payments", ['amount' => 400, 'received_into' => 'cash', 'type' => 'payment'])
            ->assertSessionHas('success');

        $p = $r->payments()->where('amount', 600)->first();
        $this->assertEquals($ebirr->id, $p->payment_account_id);
        $this->assertEquals('mobile_money', $p->method);
        $this->assertEquals('Jafer', $p->payer_name);
        $this->assertNull($r->payments()->where('amount', 400)->first()->payment_account_id);
        $this->assertEquals('cash', $r->payments()->where('amount', 400)->first()->method);

        // History, booking page, statement and reports show the account
        $this->assertStringContainsString('E-birr · 0915000000 · Abdi N. · 2600563350 · paid by Jafer', $r->activities()->where('action', 'payment')->where('amount', 600)->first()->details);
        $this->get("/reservations/{$r->id}")->assertSee('paid by Jafer');
        $this->get("/setup/accounts/{$ebirr->id}")->assertOk()->assertSee('2600563350')->assertSee($r->code);
        $this->get("/setup/accounts/{$cbe->id}")->assertOk()->assertSee('No payments into this account');
        $this->get('/reports/daily')->assertOk()->assertSee('Received into')->assertSee('E-birr · 0915000000 · Abdi N.');
        $this->get('/reports')->assertOk()->assertSee('Payments by account');
        $this->get('/setup/accounts')->assertSee('600.00');

        // An account with payments can't be deleted, only deactivated
        $this->delete("/setup/accounts/{$ebirr->id}")->assertSessionHas('error');
        $this->delete("/setup/accounts/{$cbe->id}")->assertSessionHas('success');
    }

    public function test_deposit_at_booking_goes_into_an_account(): void
    {
        $acc = PaymentAccount::create(['kind' => 'bank', 'provider' => 'Coop Bank', 'account_name' => 'Solkeb', 'account_number' => '1020']);
        $admin = $this->admin();
        $this->actingAs($admin)->get('/reservations/create')->assertSee('Coop Bank · 1020 · Solkeb');

        $room = Room::where('number', '102')->first();
        $base = ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [$room->id], 'adults' => 1, 'source' => 'corporate',
            'guest' => ['first_name' => 'Liya', 'phone' => '0911444555'], 'deposit' => 1000];
        $this->post('/reservations', $base + ['deposit_into' => $acc->id])->assertSessionHasErrors('deposit_reference');
        $this->assertEquals(0, Reservation::count());

        $this->post('/reservations', $base + ['deposit_into' => $acc->id, 'deposit_reference' => 'FT26213G826K', 'deposit_payer' => 'UNICEF'])->assertSessionHasNoErrors();
        $p = Reservation::first()->payments()->first();
        $this->assertEquals($acc->id, $p->payment_account_id);
        $this->assertEquals('bank_transfer', $p->method);
        $this->assertEquals('UNICEF', $p->payer_name);
    }
}
