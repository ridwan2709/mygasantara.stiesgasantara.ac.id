<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_honorarium', function (Blueprint $table) {
            $table->id();
            $table->enum('basis_tarif', ['per_sks', 'per_kehadiran']);
            $table->decimal('nominal', 15, 2);
            $table->date('berlaku_mulai');
            $table->date('berlaku_sampai')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['berlaku_mulai', 'berlaku_sampai']);
        });

        Schema::create('kehadiran_mengajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();
            $table->foreignId('mata_kuliah_id')->constrained('mata_kuliah')->restrictOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->date('tanggal');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->enum('status_hadir', ['hadir', 'izin', 'sakit', 'alpa'])->default('hadir');
            $table->enum('status_verifikasi', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('diverifikasi_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();
            $table->index(['dosen_id', 'tanggal', 'status_verifikasi'], 'mengajar_dosen_tanggal_verifikasi_idx');
            $table->index(['tanggal', 'status_verifikasi']);
        });

        Schema::create('insentif_manual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();
            $table->string('jenis_insentif');
            $table->decimal('nominal', 15, 2);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->foreignId('input_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['dosen_id', 'tanggal']);
        });

        Schema::create('honorarium', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dosen_id')->constrained('dosen')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->unsignedInteger('total_kehadiran')->default(0);
            $table->decimal('total_honor_mengajar', 15, 2)->default(0);
            $table->decimal('total_insentif_manual', 15, 2)->default(0);
            $table->decimal('total_penerimaan', 15, 2)->default(0);
            $table->enum('status_pembayaran', ['draft', 'paid'])->default('draft');
            $table->date('tanggal_bayar')->nullable();
            $table->timestamp('direkap_pada')->nullable();
            $table->timestamps();
            $table->unique(['dosen_id', 'tahun', 'bulan']);
            $table->index(['tahun', 'bulan', 'status_pembayaran']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('honorarium');
        Schema::dropIfExists('insentif_manual');
        Schema::dropIfExists('kehadiran_mengajar');
        Schema::dropIfExists('tarif_honorarium');
    }
};
