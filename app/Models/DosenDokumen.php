<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DosenDokumen extends Model
{
    protected $table = 'dosen_dokumen';

    protected $fillable = [
        'dosen_id',
        'nama_dokumen',
        'link_url',
    ];

    public function dosen(): BelongsTo
    {
        return $this->belongsTo(Dosen::class);
    }
}
