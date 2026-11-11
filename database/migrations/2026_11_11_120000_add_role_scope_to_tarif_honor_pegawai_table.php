<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarif_honor_pegawai', function (Blueprint $table): void {
            $table->string('role')->nullable();
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('tarif_honor_pegawai')->whereNotNull('role')->delete();

        Schema::table('tarif_honor_pegawai', function (Blueprint $table): void {
            $table->dropColumn('role');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
