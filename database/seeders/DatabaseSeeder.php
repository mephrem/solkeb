<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Change this password after first login
        User::updateOrCreate(
            ['email' => 'admin@solkebhotel.com'],
            ['name' => 'Administrator', 'password' => 'password', 'role' => 'admin', 'is_active' => true]
        );

        $this->call([
            RoomSeeder::class,
            ServiceSeeder::class,
        ]);
    }
}
