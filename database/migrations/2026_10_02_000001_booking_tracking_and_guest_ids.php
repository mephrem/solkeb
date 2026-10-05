<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who did what: the logged-in staff member is recorded automatically
        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
            $table->foreignId('checked_out_by')->nullable()->after('checked_out_at')->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->after('checked_out_by')->constrained('users')->nullOnDelete();
            $table->string('group_color', 7)->nullable()->after('source'); // rooms of one organisation booking share it
        });

        // Each room of a booking gets its own guest at check-in (organisation bookings)
        Schema::table('reservation_room', function (Blueprint $table) {
            $table->foreignId('guest_id')->nullable()->after('room_id')->constrained()->nullOnDelete();
        });

        // Photos of the guest's ID card, front and back (stored privately, not in the public web folder)
        Schema::table('guests', function (Blueprint $table) {
            $table->string('id_front_path')->nullable()->after('id_number');
            $table->string('id_back_path')->nullable()->after('id_front_path');
        });

        // History of every action on a booking, with the staff member who did it
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 30)->index();
            $table->string('details', 300)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
        Schema::table('guests', fn (Blueprint $t) => $t->dropColumn(['id_front_path', 'id_back_path']));
        Schema::table('reservation_room', fn (Blueprint $t) => $t->dropConstrainedForeignId('guest_id'));
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('checked_in_by');
            $t->dropConstrainedForeignId('checked_out_by');
            $t->dropConstrainedForeignId('cancelled_by');
            $t->dropColumn('group_color');
        });
    }
};
