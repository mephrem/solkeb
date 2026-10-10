<?php

namespace Tests\Feature;

use App\Models\Guest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    public function test_guest_fields_on_the_booking_and_guest_forms(): void
    {
        $this->actingAs(User::where('email', 'admin@solkebhotel.com')->first());

        foreach (['/reservations/create', '/guests/create'] as $url) {
            $this->get($url)->assertOk()
                ->assertDontSee("Grandfather's name")->assertDontSee('Company / organisation')->assertDontSee('TIN')
                ->assertSee('<option value="Ethiopia" selected>', false)->assertSee('<option value="Somalia"', false)->assertSee('<option value="Sweden"', false);
        }

        // Guest card is in the right-hand column, above Stay details
        $page = $this->get('/reservations/create')->getContent();
        $this->assertTrue(strpos($page, 'Choose rooms') < strpos($page, '<h2>Guest</h2>'));
        $this->assertTrue(strpos($page, '<h2>Guest</h2>') < strpos($page, 'Stay details'));

        // An older guest whose nationality is not in the list keeps it
        $g = Guest::create(['first_name' => 'Old', 'phone' => '0911000011', 'nationality' => 'Somali']);
        $this->get("/guests/{$g->id}/edit")->assertSee('<option value="Somali" selected>', false);
    }
}
