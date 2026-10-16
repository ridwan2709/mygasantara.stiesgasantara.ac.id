<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsentifManual extends Model
{
    protected $table = 'insentif_manual';

    protected $fillable = [
        'dosen_id',
        'jenis_insentif',
        'nominal',
        'tanggal',
        'keterangan',
        'input_by',
    ];

    protected function casts(): array
    {
        return [
            'nominal' => 'decimal:2',
            'tanggal' => 'date',
        ];
    }

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }

    public function inputOleh(): BelongsTo
    {
        return $this->belongsTo(User::class, 'input_by');
    }
}
