<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TagihanMahasiswa extends Model
{
    public const STATUS_BELUM_BAYAR = 'belum_bayar';

    public const STATUS_SUDAH_BAYAR = 'sudah_bayar';

    public const STATUS_BATAL = 'batal';

    protected $table = 'tagihan_mahasiswa';

    protected $fillable = [
        'mahasiswa_id',
        'created_by',
        'nama',
        'jumlah',
        'jatuh_tempo',
        'keterangan',
        'status',
        'paid_at',
        'wa_sent_at',
        'wa_error',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'jatuh_tempo' => 'date',
            'paid_at' => 'datetime',
            'wa_sent_at' => 'datetime',
        ];
    }

    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
