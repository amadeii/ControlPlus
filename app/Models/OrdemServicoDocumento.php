<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoDocumento extends Model
{
    protected $table = 'ordem_servico_documentos';

    protected $fillable = [
        'ordem_servico_id',
        'tipo',
        'arquivo',
        'caminho',
        'gerado_em',
        'user_id',
    ];

    protected $casts = [
        'gerado_em' => 'datetime',
    ];

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
