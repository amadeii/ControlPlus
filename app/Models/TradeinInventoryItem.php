<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TradeinInventoryItem extends Model
{
    use HasFactory;

    public const STATUS_PENDING_TRANSFER = 'pending_transfer';
    public const STATUS_EM_ASSISTENCIA = 'em_assistencia';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'empresa_id',
        'tradein_id',
        'cliente_id',
        'descricao_item',
        'produto_id',
        'serial',
        'valor',
        'status',
        'observacao_tecnica',
        'created_by_user_id',
    ];

    protected $casts = [
        'valor' => 'float',
    ];

    public function tradein()
    {
        return $this->belongsTo(Tradein::class, 'tradein_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function custoPecaOsLancamentos()
    {
        return $this->hasMany(TradeinInventoryItemCustoPecaOsLancamento::class, 'tradein_inventory_item_id')
            ->orderBy('id');
    }

    public function ordensServico()
    {
        return $this->hasMany(OrdemServico::class, 'tradein_inventory_item_id')
            ->orderByDesc('id');
    }
}
