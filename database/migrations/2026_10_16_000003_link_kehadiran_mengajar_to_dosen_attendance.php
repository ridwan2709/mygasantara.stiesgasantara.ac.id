<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kehadiran_mengajar', function (Blueprint $table) {
            $table->foreignId('kehadiran_dosen_id')
                ->nullable()
                ->unique()
                ->constrained('kehadiran_dosen')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kehadiran_mengajar', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kehadiran_dosen_id');
        });
    }
};
