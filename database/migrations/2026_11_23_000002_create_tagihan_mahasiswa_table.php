<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan_mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mahasiswa_id')->constrained('mahasiswa')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('nama');
            $table->decimal('jumlah', 15, 2);
            $table->date('jatuh_tempo');
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('belum_bayar');
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('wa_sent_at')->nullable();
            $table->text('wa_error')->nullable();
            $table->timestamps();

            $table->index(['mahasiswa_id', 'status']);
            $table->index(['status', 'jatuh_tempo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan_mahasiswa');
    }
};
