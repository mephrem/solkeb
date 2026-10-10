<?php

namespace Tests\Feature;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomType;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@solkebhotel.com')->first();
    }

    public function test_pages_and_roles(): void
    {
        Cache::flush();
        $this->actingAs($this->admin());
        foreach (['/setup/rooms', '/setup/room-types/create', '/setup/rooms/create', '/setup/rooms/1/edit', '/setup/room-types/1/edit',
            '/setup/services', '/setup/services/create', '/setup/services/1/edit', '/setup/settings', '/setup/users',
            '/setup/users/create', '/setup/users/1/edit', '/account'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/')->assertSee('Staff accounts')->assertSee('Hotel settings');

        $mgr = User::create(['name' => 'Manager M', 'email' => 'm@x.com', 'password' => 'secret123', 'role' => 'manager']);
        $this->actingAs($mgr)->get('/setup/rooms')->assertOk();
        $this->actingAs($mgr)->get('/setup/users')->assertForbidden();
        $this->actingAs($mgr)->get('/setup/settings')->assertForbidden();
        $this->actingAs($mgr)->get('/')->assertSee('Rooms &amp; rates', false)->assertDontSee('Staff accounts');

        $rec = User::create(['name' => 'Rec R', 'email' => 'r@x.com', 'password' => 'secret123', 'role' => 'receptionist']);
        $this->actingAs($rec)->get('/setup/rooms')->assertForbidden();
        $this->actingAs($rec)->get('/account')->assertOk();
    }

    public function test_staff_accounts(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->post('/setup/users', ['name' => 'Hodan Ali', 'email' => 'hodan@solkeb.com', 'role' => 'receptionist',
            'password' => 'short', 'password_confirmation' => 'short', 'is_active' => 1])->assertSessionHasErrors('password');
        $this->post('/setup/users', ['name' => 'Hodan Ali', 'email' => 'hodan@solkeb.com', 'role' => 'receptionist',
            'password' => 'welcome123', 'password_confirmation' => 'welcome123', 'is_active' => 1])->assertRedirect('/setup/users');

        // Only admin can't demote/disable themselves into a lockout
        $this->put("/setup/users/{$admin->id}", ['name' => 'Administrator', 'email' => $admin->email, 'role' => 'manager', 'is_active' => 1])
            ->assertSessionHas('error');
        $this->assertEquals('admin', $admin->fresh()->role);

        // Disabled users can't log in
        $hodan = User::where('email', 'hodan@solkeb.com')->first();
        $this->put("/setup/users/{$hodan->id}", ['name' => 'Hodan Ali', 'email' => $hodan->email, 'role' => 'receptionist']); // unticked
        $this->assertFalse($hodan->fresh()->is_active);
        auth()->logout();
        $this->post('/login', ['email' => 'hodan@solkeb.com', 'password' => 'welcome123'])->assertSessionHasErrors('email');

        // Password change on own account
        $this->actingAs($admin)->put('/account/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHasErrors('current_password');
        $this->actingAs($admin)->put('/account/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])
            ->assertSessionHas('success');
        auth()->logout();
        $this->post('/login', ['email' => $admin->email, 'password' => 'newpass123'])->assertRedirect('/');
    }

    public function test_rooms_services_settings(): void
    {
        Cache::flush();
        $this->actingAs($this->admin());

        // New room type + room
        $this->post('/setup/room-types', ['name' => 'Family', 'base_rate' => 3200, 'max_adults' => 4, 'max_children' => 2,
            'amenities' => 'WiFi, TV , Fridge', 'is_active' => 1])->assertRedirect('/setup/rooms');
        $fam = RoomType::where('name', 'Family')->first();
        $this->assertEquals(['WiFi', 'TV', 'Fridge'], $fam->amenities);
        $this->post('/setup/rooms', ['number' => '401', 'floor' => 4, 'room_type_id' => $fam->id])->assertRedirect('/setup/rooms');
        $this->post('/setup/rooms', ['number' => '401', 'floor' => 4, 'room_type_id' => $fam->id])->assertSessionHasErrors('number');
        $this->assertEquals(3200, Room::where('number', '401')->first()->rate());

        // Price change: existing bookings keep their rate
        $room = Room::where('number', '101')->first();
        $this->post('/reservations', ['check_in_date' => today()->toDateString(), 'check_out_date' => today()->addDay()->toDateString(),
            'rooms' => [$room->id], 'adults' => 1, 'source' => 'walk_in', 'guest' => ['first_name' => 'A', 'phone' => '0911000011']]);
        $std = $room->roomType;
        $this->put("/setup/room-types/{$std->id}", ['name' => 'Standard', 'base_rate' => 1700, 'max_adults' => 2, 'max_children' => 1, 'is_active' => 1]);
        $this->assertEquals(1500, Reservation::first()->rooms->first()->pivot->nightly_rate);
        $this->assertEquals(1700, $room->fresh()->rate());

        // Room with history can't be deleted; type with rooms can't be deleted
        $this->delete("/setup/rooms/{$room->id}")->assertSessionHas('error');
        $this->delete("/setup/room-types/{$std->id}")->assertSessionHas('error');
        $this->delete('/setup/rooms/'.Room::where('number', '401')->value('id'))->assertSessionHas('success');

        // Hidden room type is not offered for booking
        $this->put("/setup/room-types/{$fam->id}", ['name' => 'Family', 'base_rate' => 3200, 'max_adults' => 4, 'max_children' => 2]);
        $this->post('/setup/rooms', ['number' => '402', 'floor' => 4, 'room_type_id' => $fam->id]);
        $this->get('/reservations/create')->assertDontSee('Family');

        // Services
        $this->post('/setup/services', ['name' => 'Camel milk', 'category' => 'restaurant', 'price' => 120, 'is_active' => 1])->assertRedirect('/setup/services');
        $s = Service::where('name', 'Camel milk')->first();
        $this->put("/setup/services/{$s->id}", ['name' => 'Camel milk', 'category' => 'restaurant', 'price' => 150]);
        $this->assertFalse($s->fresh()->is_active);
        $this->get('/reservations/'.Reservation::first()->id)->assertDontSee('Camel milk ·');

        // Settings appear on the bill
        $this->put('/setup/settings', ['hotel_name' => 'Solkeb International Hotel', 'city' => 'Jijiga', 'vat_percent' => 15,
            'check_in_time' => '14:00', 'check_out_time' => '12:00', 'booking_prefix' => 'slk', 'tin' => '0012345678',
            'phone' => '+251 25 775 0000', 'invoice_footer' => 'Mahadsanid!'])->assertSessionHas('success');
        $this->assertEquals('SLK', Setting::get('booking_prefix'));
        $this->get('/reservations/'.Reservation::first()->id.'/invoice')
            ->assertSee('Solkeb International Hotel')->assertSee('0012345678')->assertSee('Mahadsanid!');
        $this->put('/setup/settings', ['hotel_name' => '', 'city' => 'Jijiga', 'vat_percent' => 200, 'check_in_time' => '2pm',
            'check_out_time' => '12:00', 'booking_prefix' => 'SLK'])->assertSessionHasErrors(['hotel_name', 'vat_percent', 'check_in_time']);
    }
}
