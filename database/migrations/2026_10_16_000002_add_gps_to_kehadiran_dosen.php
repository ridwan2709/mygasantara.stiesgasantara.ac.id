<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kehadiran_dosen', function (Blueprint $table) {
            $table->decimal('latitude_masuk', 10, 7)->nullable()->after('jam_masuk');
            $table->decimal('longitude_masuk', 10, 7)->nullable()->after('latitude_masuk');
            $table->decimal('latitude_keluar', 10, 7)->nullable()->after('jam_keluar');
            $table->decimal('longitude_keluar', 10, 7)->nullable()->after('latitude_keluar');
        });

        Schema::create('pengaturan_gps_kehadiran', function (Blueprint $table) {
            $table->id();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('radius_meter')->default(100);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_gps_kehadiran');

        Schema::table('kehadiran_dosen', function (Blueprint $table) {
            $table->dropColumn([
                'latitude_masuk',
                'longitude_masuk',
                'latitude_keluar',
                'longitude_keluar',
            ]);
        });
    }
};
