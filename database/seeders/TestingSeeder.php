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
        // Never on the live system: training / demo data only
        if (app()->environment('production')) {
            $this->command?->error('Test data is for training and testing only. It does not run on a production installation (APP_ENV=production).');

            return;
        }

        DatabaseSeeder::createAdmin();

        $this->call([
            TestRoomSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
