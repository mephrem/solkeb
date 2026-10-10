<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Starting data for the automated tests: the same as DatabaseSeeder, but with the fixed test rooms
 * (TestRoomSeeder) instead of the hotel's real rooms. Chosen in tests/TestCase.php.
 */
class TestingSeeder extends Seeder
{
    public function run(): void
    {
        DatabaseSeeder::createAdmin();

        $this->call([
            TestRoomSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
