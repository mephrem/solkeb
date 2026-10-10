<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\PaymentAccount;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function creditStay(Organization $org, string $room, string $name): Reservation
    {
        $this->post('/reservations', [
            'check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [Room::where('number', $room)->value('id')], 'adults' => 1, 'source' => 'corporate',
            'guest' => ['first_name' => $name, 'phone' => '0911'.$room.'000'], 'organization_id' => $org->id, 'on_credit' => 1,
        ])->assertSessionHasNoErrors();
        $r = Reservation::latest('id')->first();
        $this->post("/reservations/{$r->id}/check-in", $this->checkInPayload($r))->assertSessionHas('success');
        $this->post("/reservations/{$r->id}/check-out")->assertSessionHas('success');

        return $r->fresh();
    }

    public function test_one_transfer_pays_several_credit_invoices_oldest_first(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());
        $org = Organization::create(['name' => 'UNICEF Jijiga', 'credit_allowed' => true]);
        $cbe = PaymentAccount::create(['kind' => 'bank', 'provider' => 'CBE', 'account_name' => 'Solkeb Hotel', 'account_number' => '1000503354938']);
        $a = $this->creditStay($org, '101', 'Abdi');   // 1,500 (Standard)
        $b = $this->creditStay($org, '201', 'Hodan');  // 1,500
        $owed = $a->balance() + $b->balance();

        $this->get("/organizations/{$org->id}")->assertSee('Receive payment')->assertSee($a->credit_invoice_no)->assertSee(number_format($owed, 2, '.', ''));

        $base = ['received_into' => $cbe->id, 'reference' => 'FT26300ABC', 'ids' => [$a->id, $b->id]];
        // Checks: account, transaction number, ticked invoices, not more than owed
        $this->post("/organizations/{$org->id}/payments", ['amount' => 100, 'method' => 'bank_transfer', 'reference' => 'X', 'ids' => [$a->id]])->assertSessionHasErrors('received_into');
        $this->post("/organizations/{$org->id}/payments", ['amount' => 100, 'received_into' => $cbe->id, 'ids' => [$a->id]])->assertSessionHasErrors('reference');
        $this->post("/organizations/{$org->id}/payments", ['amount' => 100, 'received_into' => $cbe->id, 'reference' => 'X'])->assertSessionHasErrors('ids');
        $this->post("/organizations/{$org->id}/payments", ['amount' => $owed + 1] + $base)->assertSessionHasErrors('amount');
        $this->assertEquals(0, $a->payments()->count() + $b->payments()->count());

        // Part payment: the older invoice is paid in full, the rest goes to the newer one
        $bDue = $b->balance();
        $this->post("/organizations/{$org->id}/payments", ['amount' => $a->balance() + 500] + $base)
            ->assertSessionHas('success', fn ($m) => str_contains($m, $a->credit_invoice_no.' paid in full') && str_contains($m, $b->credit_invoice_no.' part paid'));
        $this->assertEquals(0.0, $a->fresh()->balance());
        $this->assertEquals(round($bDue - 500, 2), $b->fresh()->balance());
        $pa = $a->payments()->first();
        $this->assertEquals([$cbe->id, 'FT26300ABC', 'UNICEF Jijiga'], [$pa->payment_account_id, $pa->reference, $pa->payer_name]);
        $this->assertEquals('FT26300ABC', $b->payments()->first()->reference);

        // The rest arrives later
        $rest = $b->fresh()->balance();
        $this->get("/organizations/{$org->id}")->assertSee(number_format($rest, 2, '.', ''))->assertDontSee('name="ids[]" value="'.$a->id.'" checked', false);
        $this->post("/organizations/{$org->id}/payments", ['amount' => $rest, 'received_into' => $cbe->id, 'reference' => 'FT26310XYZ', 'ids' => [$b->id]])->assertSessionHas('success');
        $this->assertEquals(0.0, $b->fresh()->balance());
        $this->get("/organizations/{$org->id}")->assertSee('Nothing owed')->assertDontSee('Receive payment');

        // The bank statement shows every line under its transaction number
        $this->get("/setup/accounts/{$cbe->id}")->assertSee('FT26300ABC')->assertSee('FT26310XYZ');
    }
}
