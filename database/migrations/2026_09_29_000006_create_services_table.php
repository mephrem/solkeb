<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Things that can be charged to a room besides the room itself
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // Breakfast, Laundry, Airport transfer, Hall rent
            $table->enum('category', ['restaurant', 'bar', 'laundry', 'transport', 'hall', 'other'])
                ->default('other');
            $table->decimal('price', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
