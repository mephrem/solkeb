<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

class RoomSeeder extends Seeder
{
    /**
     * Sample layout: edit the rates and room numbers to match Solkeb's real rooms.
     */
    public function run(): void
    {
        $types = [
            'Standard' => ['rate' => 1500, 'adults' => 2, 'children' => 1, 'desc' => 'Queen bed, private bathroom, hot shower'],
            'Twin' => ['rate' => 1800, 'adults' => 2, 'children' => 0, 'desc' => 'Two single beds'],
            'Deluxe' => ['rate' => 2500, 'adults' => 2, 'children' => 1, 'desc' => 'King bed, larger room, work desk'],
            'Suite' => ['rate' => 4000, 'adults' => 3, 'children' => 2, 'desc' => 'Bedroom with separate sitting room'],
        ];

        foreach ($types as $name => $t) {
            RoomType::updateOrCreate(['name' => $name], [
                'description' => $t['desc'],
                'max_adults' => $t['adults'],
                'max_children' => $t['children'],
                'base_rate' => $t['rate'],
                'amenities' => ['WiFi', 'TV', 'Air conditioning', 'Hot water'],
            ]);
        }

        // floor => [room type => how many rooms]
        $layout = [
            1 => ['Standard' => 6, 'Twin' => 4],
            2 => ['Standard' => 4, 'Twin' => 2, 'Deluxe' => 4],
            3 => ['Deluxe' => 4, 'Suite' => 2],
        ];

        foreach ($layout as $floor => $rooms) {
            $n = 1;
            foreach ($rooms as $typeName => $count) {
                $typeId = RoomType::where('name', $typeName)->value('id');
                for ($i = 0; $i < $count; $i++, $n++) {
                    Room::updateOrCreate(
                        ['number' => $floor.str_pad((string) $n, 2, '0', STR_PAD_LEFT)],
                        ['room_type_id' => $typeId, 'floor' => $floor]
                    );
                }
            }
        }
    }
}
