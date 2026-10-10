<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bill lines get a new kind, "half_day" (late check-out until the afternoon): the enum becomes a string. */
    public function up(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->string('type', 20)->default('other')->change(); // room | service | other | discount | half_day | tax
        });
    }

    public function down(): void
    {
        Schema::table('charges', function (Blueprint $table) {
            $table->enum('type', ['room', 'service', 'discount', 'tax', 'other'])->default('service')->change();
        });
    }
};
