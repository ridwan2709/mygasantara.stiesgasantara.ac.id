<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kehadiran_dosen', function (Blueprint $table): void {
            $table->foreignId('user_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        Schema::table('kehadiran_dosen', function (Blueprint $table): void {
            $table->foreignId('dosen_id')->nullable()->change();
            $table->foreignId('jadwal_kuliah_id')->nullable()->change();
            $table->unique(['user_id', 'tanggal'], 'kehadiran_dosen_user_tanggal_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('kehadiran_dosen')->whereNotNull('user_id')->exists()) {
            throw new LogicException('Pegawai attendance records must be migrated before rolling back this migration.');
        }

        if (DB::table('kehadiran_dosen')->whereNull('dosen_id')->orWhereNull('jadwal_kuliah_id')->exists()) {
            throw new LogicException('Attendance records with nullable lecturer or schedule fields must be migrated before rolling back this migration.');
        }

        Schema::table('kehadiran_dosen', function (Blueprint $table): void {
            $table->dropUnique('kehadiran_dosen_user_tanggal_unique');
            $table->dropConstrainedForeignId('user_id');
            $table->foreignId('dosen_id')->nullable(false)->change();
            $table->foreignId('jadwal_kuliah_id')->nullable(false)->change();
        });
    }
};
