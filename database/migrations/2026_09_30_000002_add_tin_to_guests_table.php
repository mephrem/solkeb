<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Taxpayer Identification Number, printed on the bill for businesses and NGOs. */
    public function up(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->string('tin', 20)->nullable()->after('company')->index();
        });
    }

    public function down(): void
    {
        Schema::table('guests', function (Blueprint $table) {
            $table->dropIndex(['tin']);
            $table->dropColumn('tin');
        });
    }
};
