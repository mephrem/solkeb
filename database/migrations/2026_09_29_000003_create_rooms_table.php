<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_type_id')->constrained()->restrictOnDelete();
            $table->string('number', 10)->unique();       // 101, 102, 201 ...
            $table->unsignedTinyInteger('floor')->default(1);
            $table->enum('status', ['available', 'occupied', 'reserved', 'out_of_order'])
                ->default('available');
            $table->enum('housekeeping_status', ['clean', 'dirty', 'inspected', 'in_progress'])
                ->default('clean');
            $table->decimal('rate_override', 10, 2)->nullable(); // if this room differs from its type
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
