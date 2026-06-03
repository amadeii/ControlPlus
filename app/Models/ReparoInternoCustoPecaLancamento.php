<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReparoInternoCustoPecaLancamento extends Model
{
    use HasFactory;

    protected $table = 'reparo_interno_custo_peca_lancamentos';

    protected $fillable = [
        'empresa_id',
        'reparo_interno_id',
        'reparo_interno_linha_produto_id',
        'tradein_inventory_item_id',
        'produto_dispositivo_id',
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

    public function reparoInterno(): BelongsTo
    {
        return $this->belongsTo(ReparoInterno::class, 'reparo_interno_id');
    }

    public function tradeinInventoryItem(): BelongsTo
    {
        return $this->belongsTo(TradeinInventoryItem::class, 'tradein_inventory_item_id');
    }

    public function peca(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_peca_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
