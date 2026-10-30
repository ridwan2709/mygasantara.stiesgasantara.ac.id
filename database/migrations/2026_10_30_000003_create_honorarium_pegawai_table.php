<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('honorarium_pegawai', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_pegawai');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('total_kehadiran');
            $table->decimal('total_honor', 15, 2);
            $table->string('status_pembayaran', 20)->default('paid');
            $table->date('tanggal_bayar');
            $table->foreignId('dibayar_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'tahun', 'bulan']);
            $table->index(['tahun', 'bulan', 'status_pembayaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honorarium_pegawai');
    }
};
