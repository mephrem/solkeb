<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('father_name')->nullable();    // Ethiopian naming: given + father + grandfather
            $table->string('grandfather_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('phone', 20)->index();
            $table->string('email')->nullable();
            $table->string('nationality')->default('Ethiopian');
            $table->enum('id_type', ['kebele_id', 'national_id', 'passport', 'driving_license', 'other'])
                ->nullable();
            $table->string('id_number')->nullable();
            $table->string('city')->nullable();
            $table->string('company')->nullable();        // for corporate / NGO guests
            $table->boolean('is_vip')->default(false);
            $table->boolean('is_blacklisted')->default(false);
            $table->text('notes')->nullable();            // preferences, allergies, etc.
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
