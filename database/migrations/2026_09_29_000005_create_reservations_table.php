<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();         // e.g. SLK-260929-0001
            $table->foreignId('guest_id')->constrained()->restrictOnDelete();
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->unsignedTinyInteger('adults')->default(1);
            $table->unsignedTinyInteger('children')->default(0);
            $table->enum('status', ['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled', 'no_show'])
                ->default('confirmed');
            $table->enum('source', ['walk_in', 'phone', 'website', 'booking_com', 'agent', 'corporate'])
                ->default('walk_in');
            $table->text('special_requests')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['check_in_date', 'check_out_date']);
            $table->index('status');
        });

        // A reservation can hold one or more rooms (families, groups)
        Schema::create('reservation_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->restrictOnDelete();
            $table->decimal('nightly_rate', 10, 2);       // rate locked in at booking time
            $table->timestamps();

            $table->unique(['reservation_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_room');
        Schema::dropIfExists('reservations');
    }
};
