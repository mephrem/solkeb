<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Nationality is now picked from a list of country names: "Ethiopian" becomes "Ethiopia". */
    public function up(): void
    {
        DB::table('guests')->whereIn('nationality', ['Ethiopian', 'ethiopian', 'ETHIOPIAN', ''])->update(['nationality' => 'Ethiopia']);
        Schema::table('guests', fn (Blueprint $t) => $t->string('nationality')->default('Ethiopia')->change());
    }

    public function down(): void
    {
        DB::table('guests')->where('nationality', 'Ethiopia')->update(['nationality' => 'Ethiopian']);
        Schema::table('guests', fn (Blueprint $t) => $t->string('nationality')->default('Ethiopian')->change());
    }
};
