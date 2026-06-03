<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoAssistenciaChecklistFisicoItem extends Model
{
    protected $table = 'ordem_servico_assistencia_checklist_fisico_items';

    protected $fillable = [
        'ordem_servico_id',
        'item_codigo',
        'titulo',
        'estado',
        'observacao',
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
