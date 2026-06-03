<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditOrdemServicoAlteracao extends Model
{
    protected $table = 'audit_ordem_servico_alteracoes';

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'evento',
        'user_id',
        'ip_address',
        'user_agent',
        'session_id',
        'valores_antes_json',
        'valores_depois_json',
        'diff_json',
        'snapshot_exclusao_json',
        'motivo_auditoria',
    ];

    protected $casts = [
        'valores_antes_json' => 'array',
        'valores_depois_json' => 'array',
        'diff_json' => 'array',
        'snapshot_exclusao_json' => 'array',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
