<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Honorarium extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PAID = 'paid';

    protected $table = 'honorarium';

    protected $fillable = [
        'dosen_id',
        'user_id',
        'nama_pegawai',
        'dibayar_oleh',
        'bulan',
        'tahun',
        'total_kehadiran',
        'total_honor_mengajar',
        'total_insentif_manual',
        'total_penerimaan',
        'status_pembayaran',
        'tanggal_bayar',
        'direkap_pada',
    ];

    protected function casts(): array
    {
        return [
            'total_honor_mengajar' => 'decimal:2',
            'total_insentif_manual' => 'decimal:2',
            'total_penerimaan' => 'decimal:2',
            'tanggal_bayar' => 'date',
            'direkap_pada' => 'datetime',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dibayarOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibayar_oleh');
    }
}
