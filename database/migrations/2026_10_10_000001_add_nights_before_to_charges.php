<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Room moves split a stay into parts: nights in the old room, then nights in the new one.
     * A room line now records how many nights of the stay came before it (0 for the room the guest checked into),
     * so re-counting the nights (early departure, overstay, extended stay) only changes the part still running.
     */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->unsignedSmallInteger('nights_before')->default(0)->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('charges', fn (Blueprint $table) => $table->dropColumn('nights_before'));
    }
};
