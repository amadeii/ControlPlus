<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistenciaOsPecaBaixa extends Model
{
    use HasFactory;

    public const STATUS_PENDENTE = 'pendente';
    public const STATUS_BAIXADO = 'baixado';
    public const STATUS_CANCELADO = 'cancelado';

    protected $table = 'assistencia_os_peca_baixas';

    protected $fillable = [
        'empresa_id',
        'ordem_servico_id',
        'produto_os_id',
        'tradein_inventory_item_id',
        'status',
        'deposito_id',
        'movimentacao_produto_id',
        'custo_lancamento_id',
        'aprovado_por_user_id',
        'baixado_em',
    ];

    protected $casts = [
        'baixado_em' => 'datetime',
    ];

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function produtoOs(): BelongsTo
    {
        return $this->belongsTo(ProdutoOs::class, 'produto_os_id');
    }

    public function tradeinInventoryItem(): BelongsTo
    {
        return $this->belongsTo(TradeinInventoryItem::class, 'tradein_inventory_item_id');
    }

    public function movimentacaoProduto(): BelongsTo
    {
        return $this->belongsTo(MovimentacaoProduto::class, 'movimentacao_produto_id');
    }

    public function custoLancamento(): BelongsTo
    {
        return $this->belongsTo(TradeinInventoryItemCustoPecaOsLancamento::class, 'custo_lancamento_id');
    }

    public function aprovadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por_user_id');
    }

    public static function statuses(): array
    {
        return [
            self::STATUS_PENDENTE,
            self::STATUS_BAIXADO,
            self::STATUS_CANCELADO,
        ];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Pendente de baixa',
            self::STATUS_BAIXADO => 'Baixada',
            self::STATUS_CANCELADO => 'Cancelada',
            default => ucfirst((string) $this->status),
        };
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'bg-warning text-dark',
            self::STATUS_BAIXADO => 'bg-success',
            self::STATUS_CANCELADO => 'bg-secondary',
            default => 'bg-dark',
        };
    }

    public function statusResumoOperacional(): string
    {
        return match ($this->status) {
            self::STATUS_PENDENTE => 'Estoque ainda nao baixado. Aguardando aprovacao administrativa.',
            self::STATUS_BAIXADO => 'Baixa fisica ja concluida e rastreada no fluxo Trade-In.',
            self::STATUS_CANCELADO => 'Pendencia cancelada antes da baixa fisica.',
            default => 'Status operacional nao mapeado.',
        };
    }
}
