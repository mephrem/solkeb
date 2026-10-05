<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    /** Sample prices in ETB: adjust to the hotel's real menu. */
    public function run(): void
    {
        $services = [
            ['Breakfast',             'restaurant', 300],
            ['Lunch',                 'restaurant', 450],
            ['Dinner',                'restaurant', 450],
            ['Soft drink',            'bar',        60],
            ['Bottled water',         'bar',        40],
            ['Coffee / Tea',          'restaurant', 50],
            ['Laundry (per item)',    'laundry',    80],
            ['Airport transfer',      'transport',  600],
            ['Meeting hall (half day)', 'hall',     5000],
            ['Meeting hall (full day)', 'hall',     9000],
            ['Extra bed',             'other',      500],
        ];

        foreach ($services as [$name, $category, $price]) {
            Service::updateOrCreate(['name' => $name], ['category' => $category, 'price' => $price]);
        }
    }
}
