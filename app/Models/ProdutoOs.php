<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProdutoOs extends Model
{
    use HasFactory;

    protected $fillable = [
        'produto_id',
        'ordem_servico_id',
        'quantidade',
        'valor',
        'subtotal',
        'descricao_livre',
        'marca_livre',
        'modelo_livre',
        'imei_serial_livre',
    ];

    public function produto()
    {
        return $this->belongsTo(Produto::class, 'produto_id');
    }

    /** Rótulo para listagem/PDF/link (cadastro OU texto livre). */
    public function descricaoLinha(): string
    {
        $this->loadMissing('produto');

        if ($this->produto_id && $this->produto) {
            return (string) $this->produto->nome;
        }

        $partes = array_filter([
            trim((string) $this->descricao_livre),
            trim((string) $this->marca_livre) !== '' ? 'Marca: ' . trim((string) $this->marca_livre) : null,
            trim((string) $this->modelo_livre) !== '' ? 'Modelo: ' . trim((string) $this->modelo_livre) : null,
            trim((string) $this->imei_serial_livre) !== ''
                ? 'IMEI/S/N: ' . trim((string) $this->imei_serial_livre)
                : null,
        ], static fn ($v) => $v !== null && $v !== '');

        return \count($partes) > 0 ? implode(' · ', $partes) : '—';
    }

    /** Indica linha apenas descritiva (sem produto cadastrado). */
    public function linhaSomenteManual(): bool
    {
        return $this->produto_id === null || (int) $this->produto_id === 0;
    }

    public function ordemServico(){
        return $this->belongsTo(OrdemServico::class, 'ordem_servico_id');
    }

    public function assistenciaPecaBaixa()
    {
        return $this->hasOne(AssistenciaOsPecaBaixa::class, 'produto_os_id');
    }
}
