<?php

namespace Tests\Feature;

use App\Http\Controllers\SettingController;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Solkeb takes no advance payment: guests pay during the stay or at check-out. */
class NoDepositTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_no_deposit_at_booking_by_default(): void
    {
        $admin = User::where('email', 'admin@solkebhotel.com')->first();
        $this->actingAs($admin)->get('/reservations/create')->assertOk()->assertDontSee('Deposit');

        // Even if a deposit is sent, nothing is recorded
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', '101')->value('id')], 'adults' => 1, 'source' => 'phone',
            'guest' => ['first_name' => 'Abdi', 'phone' => '0911000111'], 'deposit' => 500, 'deposit_method' => 'cash',
        ])->assertSessionHasNoErrors();
        $r = Reservation::first();
        $this->assertEquals(0, $r->payments()->count());

        // No payment form before the guest arrives; it appears once checked in, without a "Deposit" option
        $this->get("/reservations/{$r->id}")->assertDontSee('Record payment');
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        $this->get("/reservations/{$r->id}")->assertSee('Record payment')->assertDontSee('<option value="deposit">', false);

        // The admin can switch deposits on in Hotel settings
        $this->get('/setup/settings')->assertSee('Take a deposit');
        $settings = collect(array_keys(SettingController::FIELDS))->mapWithKeys(fn ($k) => [$k => Setting::get($k)])->all();
        $this->put('/setup/settings', ['take_deposits' => 1] + $settings)->assertSessionHas('success');
        $this->assertEquals('1', Setting::get('take_deposits'));
        $this->get('/reservations/create')->assertSee('Deposit');
        $this->put('/setup/settings', array_merge($settings, ['take_deposits' => null]))->assertSessionHas('success');
        $this->assertEquals('0', Setting::get('take_deposits'));
    }
}
