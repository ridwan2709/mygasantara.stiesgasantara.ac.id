<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kehadiran_dosen', function (Blueprint $table) {
            $table->foreignId('dicatat_oleh')
                ->nullable()
                ->after('dosen_id')
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('kehadiran_dosen', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dicatat_oleh');
        });
    }
};
