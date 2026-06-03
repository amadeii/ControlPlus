<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistenciaEstoqueAjusteManual extends Model
{
    public const TIPO_MOVIMENTACAO = 'os_ajuste_manual';

    public const MOTIVO_PERDA = 'perda';

    public const MOTIVO_QUEBRA = 'quebra';

    public const MOTIVO_DEFEITO = 'defeito';

    public const MOTIVO_DESCARTE = 'descarte';

    protected $table = 'assistencia_estoque_ajustes_manuais';

    protected $fillable = [
        'empresa_id',
        'produto_id',
        'produto_variacao_id',
        'quantidade',
        'deposito_id',
        'motivo',
        'observacao',
        'user_id',
    ];

    /** @return array<string, string> */
    public static function motivosLabels(): array
    {
        return [
            self::MOTIVO_PERDA => 'Perda',
            self::MOTIVO_QUEBRA => 'Quebra',
            self::MOTIVO_DEFEITO => 'Defeito',
            self::MOTIVO_DESCARTE => 'Descarte',
        ];
    }

    /** @return array<string, string> */
    public static function motivosParaSelect(): array
    {
        return ['' => 'Todos'] + self::motivosLabels();
    }

    public function produto(): BelongsTo
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deposito(): BelongsTo
    {
        return $this->belongsTo(Deposito::class, 'deposito_id');
    }
}
