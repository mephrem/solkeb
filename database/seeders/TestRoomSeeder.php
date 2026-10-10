<?php

namespace Database\Seeders;

use App\Models\Room;
use App\Models\RoomType;
use Illuminate\Database\Seeder;

/**
 * A small fixed set of rooms for the automated tests only (rooms 101–306, prices 1,500 to 4,000).
 * The tests are written against these numbers and prices, so they keep working whatever the hotel's real rooms are.
 */
class TestRoomSeeder extends Seeder
{
    public function run(): void
    {
        // Never on the live system: training / demo data only
        if (app()->environment('production')) {
            $this->command?->error('The test rooms are for testing only. They are not added on a production installation (APP_ENV=production).');

            return;
        }

        $types = [
            'Standard' => ['rate' => 1500, 'adults' => 2, 'children' => 1],
            'Twin' => ['rate' => 1800, 'adults' => 2, 'children' => 0],
            'Deluxe' => ['rate' => 2500, 'adults' => 2, 'children' => 1],
            'Suite' => ['rate' => 4000, 'adults' => 3, 'children' => 2],
        ];
        foreach ($types as $name => $t) {
            RoomType::updateOrCreate(['name' => $name], [
                'max_adults' => $t['adults'], 'max_children' => $t['children'], 'base_rate' => $t['rate'],
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
