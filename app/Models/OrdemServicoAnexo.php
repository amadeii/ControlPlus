<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoAnexo extends Model
{
    protected $table = 'ordem_servico_anexos';

    protected $fillable = [
        'ordem_servico_id',
        'tipo',
        'arquivo',
        'caminho',
        'mime',
        'user_id',
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
