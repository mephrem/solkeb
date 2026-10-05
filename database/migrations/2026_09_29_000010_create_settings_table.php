<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('settings')->insert([
            ['key' => 'hotel_name',     'value' => 'Solkeb Hotel', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'city',           'value' => 'Jijiga',       'created_at' => $now, 'updated_at' => $now],
            ['key' => 'region',         'value' => 'Somali Region, Ethiopia', 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'currency',       'value' => 'ETB',          'created_at' => $now, 'updated_at' => $now],
            ['key' => 'vat_percent',    'value' => '15',           'created_at' => $now, 'updated_at' => $now],
            ['key' => 'check_in_time',  'value' => '14:00',        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'check_out_time', 'value' => '12:00',        'created_at' => $now, 'updated_at' => $now],
            ['key' => 'booking_prefix', 'value' => 'SLK',          'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
