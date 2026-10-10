<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Starting data for a new installation: the admin account, Solkeb's real rooms and the price list.
 * Run with: php artisan migrate --seed
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        self::createAdmin();

        $this->call([
            RoomSeeder::class,
            ServiceSeeder::class,
        ]);
    }

    /** The first admin account. Change this password after the first login. */
    public static function createAdmin(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@solkebhotel.com'],
            ['name' => 'Administrator', 'password' => 'password', 'role' => 'admin', 'is_active' => true]
        );
    }
}
