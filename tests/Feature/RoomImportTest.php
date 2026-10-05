<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoomImportTest extends TestCase
{
    use RefreshDatabase;

    protected $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@solkebhotel.com')->first();
    }

    public function test_csv_file_with_header_preview_then_import(): void
    {
        Cache::flush();
        $this->actingAs($this->admin());
        $this->get('/setup/rooms')->assertSee('Import rooms');
        $this->get('/setup/rooms/import')->assertOk()->assertSee('Add a range');
        $tpl = $this->get('/setup/rooms/import/template')->assertOk();
        $this->assertStringContainsString('number,floor,type,price,notes', $tpl->getContent());

        // Columns in a different order, Excel BOM, mixed-case type, price with comma, blank floor, a quoted note with a comma
        $csv = "\xEF\xBB\xBFRoom No,Type,Floor,Rate,Notes\r\n"
            ."401,deluxe,,,\r\n"
            ."402,Deluxe,4,\"2,800\",\"Balcony, city view\"\r\n"
            ."403,Deluxe,4,2500,\r\n"      // same as the type rate -> no own price
            ."404,Presidential,4,,\r\n"    // unknown type
            ."401,Deluxe,4,,\r\n"          // duplicate in file
            ."101,Standard,1,,\r\n"        // already exists
            ."B7,Twin,,,\r\n"              // floor can't be worked out
            .",Twin,1,,\r\n";              // no number
        $file = UploadedFile::fake()->createWithContent('rooms.csv', $csv);

        $res = $this->post('/setup/rooms/import/preview', ['mode' => 'file', 'file' => $file])->assertOk();
        $rows = collect($res->viewData('rows'))->keyBy('line');
        $this->assertEquals(['new', 'new', 'new', 'error', 'error', 'skip', 'error', 'error'], $rows->pluck('status')->values()->all());
        $this->assertEquals(4, $rows[1]['floor']);
        $this->assertEquals('Deluxe', $rows[1]['type']);
        $this->assertEquals(2800, $rows[2]['price']);
        $this->assertEquals('Balcony, city view', $rows[2]['notes']);
        $this->assertNull($rows[3]['price']);
        $this->assertStringContainsString('Unknown room type "Presidential"', $rows[4]['message']);
        $this->assertStringContainsString('appears twice', $rows[5]['message']);
        $this->assertStringContainsString('Floor is missing', $rows[7]['message']);
        $this->assertEquals(26, Room::count()); // nothing saved by the preview

        $this->post('/setup/rooms/import')->assertRedirect('/setup/rooms')->assertSessionHas('success', '3 room(s) added.');
        $this->assertEquals(29, Room::count());
        $this->assertEquals(2800, (float) Room::where('number', '402')->value('rate_override'));
        $this->assertEquals(2500, Room::where('number', '401')->first()->rate());

        // The preview can only be used once
        $this->post('/setup/rooms/import')->assertRedirect('/setup/rooms/import')->assertSessionHas('error');
    }

    public function test_pasted_from_excel_and_update_existing(): void
    {
        Cache::flush();
        $this->actingAs($this->admin());
        Room::where('number', '101')->update(['rate_override' => 1400, 'notes' => 'Quiet']);

        // Tab-separated (what Excel puts on the clipboard), no header row, semicolon file style also accepted
        $pasted = "101\t1\tDeluxe\t\t\n102\t1\tTwin\t1750\tCorner\n501\t\tSuite\t\t";
        $rows = collect($this->post('/setup/rooms/import/preview', ['mode' => 'file', 'pasted' => $pasted, 'update_existing' => 1])->viewData('rows'));
        $this->assertEquals(['update', 'update', 'new'], $rows->pluck('status')->all());
        $this->post('/setup/rooms/import')->assertSessionHas('success', '1 room(s) added. 2 room(s) updated.');

        $r101 = Room::where('number', '101')->first();
        $this->assertEquals('Deluxe', $r101->roomType->name);
        $this->assertEquals(1400, (float) $r101->rate_override); // blank price keeps the room's own price
        $this->assertEquals('Quiet', $r101->notes);              // blank notes keep the notes
        $this->assertEquals('Corner', Room::where('number', '102')->value('notes'));
        $this->assertEquals(5, Room::where('number', '501')->value('floor'));

        $semi = "number;floor;type\n601;6;Standard\n";
        $this->assertEquals('new', $this->post('/setup/rooms/import/preview', ['mode' => 'file', 'pasted' => $semi])->viewData('rows')[0]['status']);
    }

    public function test_range_and_guards(): void
    {
        Cache::flush();
        $this->actingAs($this->admin());

        $res = $this->post('/setup/rooms/import/preview', ['mode' => 'range', 'from' => 305, 'to' => 310, 'type' => 'Suite']);
        $rows = collect($res->viewData('rows'));
        $this->assertEquals(['skip', 'skip', 'new', 'new', 'new', 'new'], $rows->pluck('status')->all()); // 305, 306 exist
        $this->assertEquals([3], $rows->pluck('floor')->unique()->values()->all());
        $this->post('/setup/rooms/import')->assertSessionHas('success', '4 room(s) added.');
        $this->assertEquals(4000, Room::where('number', '310')->first()->rate());

        // Prefix and explicit floor
        $rows = collect($this->post('/setup/rooms/import/preview', ['mode' => 'range', 'from' => 1, 'to' => 3, 'prefix' => 'B', 'floor' => 0, 'type' => 'Twin'])->viewData('rows'));
        $this->assertEquals(['B1', 'B2', 'B3'], $rows->pluck('number')->all());
        $this->assertEquals('new', $rows[0]['status']);

        // Too big a range, reversed range, empty paste, nothing importable
        $this->post('/setup/rooms/import/preview', ['mode' => 'range', 'from' => 1, 'to' => 500, 'type' => 'Twin'])->assertSessionHas('error');
        $this->post('/setup/rooms/import/preview', ['mode' => 'range', 'from' => 10, 'to' => 1, 'type' => 'Twin'])->assertSessionHasErrors('to');
        $this->post('/setup/rooms/import/preview', ['mode' => 'file', 'pasted' => ''])->assertSessionHas('error');
        $this->post('/setup/rooms/import/preview', ['mode' => 'file', 'pasted' => "101\t1\tStandard"])->assertSee('Nothing to import');

        // A room added by someone else after the preview is not duplicated
        $this->post('/setup/rooms/import/preview', ['mode' => 'file', 'pasted' => "701\t7\tStandard"]);
        Room::create(['number' => '701', 'floor' => 7, 'room_type_id' => 1]);
        $this->post('/setup/rooms/import')->assertSessionHas('error');
        $this->assertEquals(1, Room::where('number', '701')->count());

        // Receptionists can't import
        $rec = User::create(['name' => 'R', 'email' => 'r@x.com', 'password' => 'secret123', 'role' => 'receptionist']);
        $this->actingAs($rec)->get('/setup/rooms/import')->assertForbidden();
    }
}
