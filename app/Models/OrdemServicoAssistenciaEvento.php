<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoAssistenciaEvento extends Model
{
    protected $table = 'ordem_servico_assistencia_eventos';

    protected $fillable = [
        'ordem_servico_id',
        'tipo',
        'mensagem',
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
