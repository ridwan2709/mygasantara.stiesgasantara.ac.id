<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('honorarium', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('dosen_id')->constrained('users')->nullOnDelete();
            $table->string('nama_pegawai')->nullable()->after('user_id');
            $table->foreignId('dibayar_oleh')->nullable()->after('tanggal_bayar')->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('dosen_id')->nullable()->change();
            $table->unique(['user_id', 'tahun', 'bulan']);
        });

        if (Schema::hasTable('honorarium_pegawai')) {
            DB::table('honorarium_pegawai')
                ->orderBy('id')
                ->chunk(100, function ($payments): void {
                    foreach ($payments as $payment) {
                        DB::table('honorarium')->insertOrIgnore([
                            'dosen_id' => null,
                            'user_id' => $payment->user_id,
                            'nama_pegawai' => $payment->nama_pegawai,
                            'bulan' => $payment->bulan,
                            'tahun' => $payment->tahun,
                            'total_kehadiran' => $payment->total_kehadiran,
                            'total_honor_mengajar' => $payment->total_honor,
                            'total_insentif_manual' => 0,
                            'total_penerimaan' => $payment->total_honor,
                            'status_pembayaran' => $payment->status_pembayaran,
                            'tanggal_bayar' => $payment->tanggal_bayar,
                            'direkap_pada' => $payment->updated_at,
                            'dibayar_oleh' => $payment->dibayar_oleh,
                            'created_at' => $payment->created_at,
                            'updated_at' => $payment->updated_at,
                        ]);
                    }
                });

            Schema::drop('honorarium_pegawai');
        }
    }

    public function down(): void
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

        DB::table('honorarium')
            ->whereNotNull('nama_pegawai')
            ->orderBy('id')
            ->chunkById(100, function ($payments): void {
                foreach ($payments as $payment) {
                    DB::table('honorarium_pegawai')->insertOrIgnore([
                        'user_id' => $payment->user_id,
                        'nama_pegawai' => $payment->nama_pegawai ?? 'Pegawai',
                        'bulan' => $payment->bulan,
                        'tahun' => $payment->tahun,
                        'total_kehadiran' => $payment->total_kehadiran,
                        'total_honor' => $payment->total_honor_mengajar,
                        'status_pembayaran' => $payment->status_pembayaran,
                        'tanggal_bayar' => $payment->tanggal_bayar,
                        'dibayar_oleh' => $payment->dibayar_oleh,
                        'created_at' => $payment->created_at,
                        'updated_at' => $payment->updated_at,
                    ]);
                }
            });

        DB::table('honorarium')->whereNotNull('nama_pegawai')->delete();

        Schema::table('honorarium', function (Blueprint $table): void {
            $table->dropUnique(['user_id', 'tahun', 'bulan']);
            $table->dropConstrainedForeignId('dibayar_oleh');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn('nama_pegawai');
            $table->unsignedBigInteger('dosen_id')->nullable(false)->change();
        });
    }
};
