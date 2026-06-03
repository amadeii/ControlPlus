<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReparoInterno extends Model
{
    use HasFactory;

    protected $table = 'reparos_internos';

    public const STATUS_ABERTO = 'aberto';

    public const STATUS_EM_ANDAMENTO = 'em_andamento';

    public const STATUS_FINALIZADO = 'finalizado';

    public const STATUS_CANCELADO = 'cancelado';

    protected $fillable = [
        'empresa_id',
        'codigo_sequencial',
        'status',
        'tradein_inventory_item_id',
        'produto_id',
        'produto_unico_id',
        'local_id',
        'deposito_id',
        'funcionario_id',
        'observacao_tecnica',
        'usuario_id',
        'usuario_finalizacao_id',
        'usuario_cancelamento_id',
        'finalizado_at',
        'cancelado_at',
    ];

    protected $casts = [
        'finalizado_at' => 'datetime',
        'cancelado_at' => 'datetime',
    ];

    public static function statuses(): array
    {
        return [
            self::STATUS_ABERTO => 'Aberto',
            self::STATUS_EM_ANDAMENTO => 'Em andamento',
            self::STATUS_FINALIZADO => 'Finalizado',
            self::STATUS_CANCELADO => 'Cancelado',
        ];
    }

    public function tradeinInventoryItem()
    {
        return $this->belongsTo(TradeinInventoryItem::class, 'tradein_inventory_item_id');
    }

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function produtoUnico()
    {
        return $this->belongsTo(ProdutoUnico::class, 'produto_unico_id');
    }

    public function funcionario()
    {
        return $this->belongsTo(Funcionario::class, 'funcionario_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function usuarioFinalizacao()
    {
        return $this->belongsTo(User::class, 'usuario_finalizacao_id');
    }

    public function usuarioCancelamento()
    {
        return $this->belongsTo(User::class, 'usuario_cancelamento_id');
    }

    public function linhas()
    {
        return $this->hasMany(ReparoInternoLinhaProduto::class, 'reparo_interno_id');
    }

    public function eventos()
    {
        return $this->hasMany(ReparoInternoEvento::class, 'reparo_interno_id')->orderBy('id');
    }

    public function custoPecaLancamentos()
    {
        return $this->hasMany(ReparoInternoCustoPecaLancamento::class, 'reparo_interno_id')->orderBy('id');
    }

    public function isEncerrado(): bool
    {
        return \in_array($this->status, [self::STATUS_FINALIZADO, self::STATUS_CANCELADO], true);
    }

    public function permiteEditarConteudo(): bool
    {
        return \in_array($this->status, [self::STATUS_ABERTO, self::STATUS_EM_ANDAMENTO], true);
    }
}
