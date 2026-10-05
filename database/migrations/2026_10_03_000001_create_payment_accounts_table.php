<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The hotel's bank accounts and mobile wallets (several per bank are allowed)
        Schema::create('payment_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10)->default('bank'); // bank | wallet
            $table->string('provider', 60);              // CBE, Coop Bank of Oromia, E-birr, telebirr...
            $table->string('account_name', 100);         // name the account is registered in
            $table->string('account_number', 50);
            $table->string('notes', 200)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['provider', 'account_number']);
        });

        // Every non-cash payment records which account the money went into, and who paid it
        Schema::table('payments', function (Blueprint $table) {
            $table->string('method', 20)->default('cash')->change(); // was an enum: allows "mobile_money"
            $table->foreignId('payment_account_id')->nullable()->after('method')->constrained()->restrictOnDelete();
            $table->string('payer_name', 100)->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropColumn('payer_name');
        });
        Schema::dropIfExists('payment_accounts');
    }
};
