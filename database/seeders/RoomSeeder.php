<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * Solkeb Hotel's real rooms: 40 rooms on floors 2 to 5, with their types and nightly prices (ETB, VAT included).
 * Prices and rooms can be changed later in Setup → Rooms & rates. (The automated tests use TestRoomSeeder instead.)
 */
class RoomSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'VIP' => ['rate' => 4500, 'adults' => 2, 'children' => 0, 'desc' => null],
            'Standard' => ['rate' => 3000, 'adults' => 2, 'children' => 1, 'desc' => 'Queen bed, private bathroom, hot shower, work desk'],
            'Suite' => ['rate' => 3500, 'adults' => 2, 'children' => 1, 'desc' => 'King bed, larger room, work desk'],
            'Twin' => ['rate' => 4500, 'adults' => 2, 'children' => 0, 'desc' => 'Two single beds'],
        ];

        foreach ($types as $name => $t) {
            RoomType::updateOrCreate(['name' => $name], [
                'description' => $t['desc'],
                'max_adults' => $t['adults'],
                'max_children' => $t['children'],
                'base_rate' => $t['rate'],
                'amenities' => ['WiFi', 'TV', 'Hot water'],
            ]);
        }

        // Every floor: x01 is the VIP (floor 2) or Twin room, x02–x05 Standard, x06–x10 Suite
        foreach ([2, 3, 4, 5] as $floor) {
            for ($n = 1; $n <= 10; $n++) {
                $type = match (true) {
                    $n === 1 => $floor === 2 ? 'VIP' : 'Twin',
                    $n <= 5 => 'Standard',
                    default => 'Suite',
                };
                Room::updateOrCreate(
                    ['number' => $floor.str_pad((string) $n, 2, '0', STR_PAD_LEFT)],
                    ['room_type_id' => RoomType::where('name', $type)->value('id'), 'floor' => $floor]
                );
            }
        }
    }
}
