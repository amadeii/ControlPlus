<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeinInventoryItemCustoPecaOsLancamento extends Model
{
    use HasFactory;

    protected $table = 'tradein_inventory_item_custo_peca_os_lancamentos';

    protected $fillable = [
        'empresa_id',
        'tradein_inventory_item_id',
        'ordem_servico_id',
        'produto_os_id',
        'movimentacao_produto_id',
        'produto_peca_id',
        'quantidade_peca',
        'valor_compra_unitario_peca',
        'valor_custo_incremento',
        'custo_aparelho_antes',
        'custo_aparelho_depois',
        'valor_avaliado_tradein_origem',
        'user_id',
    ];

    protected $casts = [
        'quantidade_peca' => 'float',
        'valor_compra_unitario_peca' => 'float',
        'valor_custo_incremento' => 'float',
        'custo_aparelho_antes' => 'float',
        'custo_aparelho_depois' => 'float',
        'valor_avaliado_tradein_origem' => 'float',
    ];

    public function tradeinInventoryItem(): BelongsTo
    {
        return $this->belongsTo(TradeinInventoryItem::class, 'tradein_inventory_item_id');
    }

    public function ordemServico(): BelongsTo
    {
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function produtoOs(): BelongsTo
    {
        return $this->belongsTo(ProdutoOs::class, 'produto_os_id');
    }

    public function peca(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_peca_id');
    }
}
