<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KehadiranMengajar extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected $table = 'kehadiran_mengajar';

    protected $fillable = [
        'dosen_id',
        'mata_kuliah_id',
        'kelas_id',
        'tanggal',
        'jam_mulai',
        'jam_selesai',
        'status_hadir',
        'status_verifikasi',
        'diverifikasi_oleh',
        'diverifikasi_pada',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'diverifikasi_pada' => 'datetime',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function kehadiranDosen(): BelongsTo
    {
        return $this->belongsTo(KehadiranDosen::class);
    }

    public function mataKuliah(): BelongsTo
    {
        return $this->belongsTo(MataKuliah::class);
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function diverifikasiOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diverifikasi_oleh');
    }
}
