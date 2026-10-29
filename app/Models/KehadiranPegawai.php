<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KehadiranPegawai extends Model
{
    use HasFactory;

    protected $table = 'kehadiran_pegawai';

    protected $fillable = [
        'user_id',
        'tanggal',
        'jam_masuk',
        'jam_keluar',
        'latitude_masuk',
        'longitude_masuk',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
