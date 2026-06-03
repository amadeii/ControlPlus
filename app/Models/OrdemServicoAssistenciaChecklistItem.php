<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdemServicoAssistenciaChecklistItem extends Model
{
    protected $table = 'ordem_servico_assistencia_checklist_items';

    protected $fillable = [
        'ordem_servico_id',
        'item_codigo',
        'titulo',
        'feito',
        'feito_em',
        'feito_por_user_id',
    ];

    protected $casts = [
        'feito' => 'boolean',
        'feito_em' => 'datetime',
    ];

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function feitoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feito_por_user_id');
    }
}
