<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditEstoqueDetalhe extends Model
{
    protected $table = 'audit_estoque_detalhes';

    protected $fillable = [
        'empresa_id',
        'movimentacao_produto_id',
        'produto_id',
        'produto_variacao_id',
        'deposito_id',
        'tipo',
        'tipo_transacao',
        'codigo_transacao',
        'quantidade_movimentada',
        'estoque_depois',
        'estoque_antes',
        'user_id',
        'ip_address',
        'user_agent',
        'session_id',
    ];

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }

    public function movimentacaoProduto(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoProduto::class, 'movimentacao_produto_id');
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }
}
