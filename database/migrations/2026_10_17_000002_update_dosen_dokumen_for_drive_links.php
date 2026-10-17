<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dosen_dokumen')) {
            return;
        }

        Schema::table('dosen_dokumen', function (Blueprint $table): void {
            if (! Schema::hasColumn('dosen_dokumen', 'link_url')) {
                $table->string('link_url', 2048)->nullable();
            }

            if (Schema::hasColumn('dosen_dokumen', 'nama_file')) {
                $table->string('nama_file')->nullable()->change();
            }

            if (Schema::hasColumn('dosen_dokumen', 'path')) {
                $table->string('path')->nullable()->change();
            }

            if (Schema::hasColumn('dosen_dokumen', 'mime_type')) {
                $table->string('mime_type', 100)->nullable()->change();
            }

            if (Schema::hasColumn('dosen_dokumen', 'ukuran')) {
                $table->unsignedBigInteger('ukuran')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('dosen_dokumen', 'path') && Schema::hasColumn('dosen_dokumen', 'link_url')) {
            Schema::table('dosen_dokumen', function (Blueprint $table): void {
                $table->dropColumn('link_url');
            });
        }
    }
};
