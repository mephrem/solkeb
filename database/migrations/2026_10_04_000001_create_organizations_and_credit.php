<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Companies, NGOs and government offices that book rooms, some of which pay later on credit
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->string('contact_person', 100)->nullable();
            $table->string('contact_title', 100)->nullable(); // e.g. Finance Head: used on the payment request letter
            $table->string('phone', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('tin', 20)->nullable();
            $table->boolean('credit_allowed')->default(false);
            $table->unsignedSmallInteger('credit_days')->default(30); // days they have to pay a credit invoice
            $table->string('notes', 300)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->after('guest_id')->constrained()->nullOnDelete();
            $table->boolean('on_credit')->default(false)->after('organization_id'); // the organisation pays after the guest leaves
            $table->string('credit_invoice_no', 20)->nullable()->unique()->after('on_credit');
            $table->timestamp('credit_invoiced_at')->nullable()->after('credit_invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropUnique(['credit_invoice_no']);
            $table->dropColumn(['on_credit', 'credit_invoice_no', 'credit_invoiced_at']);
        });
        Schema::dropIfExists('organizations');
    }
};
