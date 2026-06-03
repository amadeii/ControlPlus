<?php

namespace App\Services;

use App\Models\Deposito;
use App\Models\MovimentacaoProduto;
use App\Models\Produto;
use App\Models\ReparoInterno;
use App\Models\ReparoInternoLinhaProduto;
use App\Utils\EstoqueUtil;
use Illuminate\Support\Facades\Auth;

class ReparoInternoEstoqueService
{
    public const TIPO_CONSUMO = 'reparo_interno_consumo_peca';

    public const TIPO_ESTORNO = 'reparo_interno_estorno_peca';

    public function __construct(private EstoqueUtil $estoqueUtil)
    {
    }

    /**
     * @throws \Throwable
     */
    public function aplicarBaixa(ReparoInterno $reparo, ReparoInternoLinhaProduto $linha, ?int $depositoIdSolicitado = null): void
    {
        $produto = Produto::find($linha->produto_id);
        if (!$produto || !$produto->gerenciar_estoque) {
            return;
        }

        if (MovimentacaoProduto::where('tipo_transacao', self::TIPO_CONSUMO)
            ->where('codigo_transacao', (int) $linha->id)->exists()) {
            return;
        }

        $localId = $reparo->local_id;
        if (!$localId && function_exists('__getLocalAtivo')) {
            $la = __getLocalAtivo();
            $localId = $la->id ?? null;
        }

        $depositoId = $depositoIdSolicitado;

        if ($depositoIdSolicitado) {
            $dep = Deposito::where('id', $depositoIdSolicitado)
                ->where('empresa_id', $reparo->empresa_id)
                ->first();
            if (!$dep) {
                throw new \Exception('Depósito inválido para esta empresa.');
            }
            $localId = (int) $dep->local_id;
        }

        if (!$localId) {
            throw new \Exception('Defina o local do reparo interno ou um local ativo para registrar a baixa de estoque.');
        }

        $quantidade = $linha->quantidade;
        $variacaoId = null;

        $this->estoqueUtil->reduzEstoque(
            (int) $linha->produto_id,
            $quantidade,
            $variacaoId,
            (int) $localId,
            $depositoId
        );

        $this->estoqueUtil->movimentacaoProduto(
            (int) $linha->produto_id,
            $quantidade,
            'reducao',
            (int) $linha->id,
            self::TIPO_CONSUMO,
            Auth::id(),
            $variacaoId,
            (int) $localId,
            $depositoId
        );

        __createLog(
            (int) $reparo->empresa_id,
            'Reparo interno',
            'cadastrar',
            '[reparo_interno_peca] #' . $reparo->codigo_sequencial . ' — ' . $produto->nome . ' qtd ' . $quantidade
        );

        app(ReparoInternoCustoAparelhoService::class)->registrarAposBaixa($reparo, $linha);
    }

    /**
     * @throws \Throwable
     */
    public function aplicarEstorno(ReparoInterno $reparo, ReparoInternoLinhaProduto $linha): void
    {
        $produto = Produto::find($linha->produto_id);
        if (!$produto || !$produto->gerenciar_estoque) {
            return;
        }

        $consumo = MovimentacaoProduto::where('tipo_transacao', self::TIPO_CONSUMO)
            ->where('codigo_transacao', (int) $linha->id)
            ->first();

        if (!$consumo) {
            return;
        }

        if (MovimentacaoProduto::where('tipo_transacao', self::TIPO_ESTORNO)
            ->where('codigo_transacao', (int) $linha->id)->exists()) {
            return;
        }

        $depositoId = $consumo->deposito_id ? (int) $consumo->deposito_id : null;
        $localId = Deposito::resolveLocalIdByDepositoId($depositoId);

        if (!$localId) {
            $localId = $reparo->local_id;
        }
        if (!$localId && function_exists('__getLocalAtivo')) {
            $la = __getLocalAtivo();
            $localId = $la->id ?? null;
        }

        if (!$localId) {
            throw new \Exception('Não foi possível resolver o local para estornar o estoque da peça.');
        }

        $quantidade = $linha->quantidade;
        $variacaoId = $consumo->produto_variacao_id;

        $this->estoqueUtil->incrementaEstoque(
            (int) $linha->produto_id,
            $quantidade,
            $variacaoId,
            (int) $localId,
            $depositoId
        );

        $this->estoqueUtil->movimentacaoProduto(
            (int) $linha->produto_id,
            $quantidade,
            'incremento',
            (int) $linha->id,
            self::TIPO_ESTORNO,
            Auth::id(),
            $variacaoId,
            (int) $localId,
            $depositoId
        );

        __createLog(
            (int) $reparo->empresa_id,
            'Reparo interno',
            'excluir',
            '[reparo_interno_peca_estorno] #' . $reparo->codigo_sequencial . ' — ' . $produto->nome
        );

        app(ReparoInternoCustoAparelhoService::class)->reverterPorLinha((int) $linha->id, (int) $reparo->empresa_id);
    }
}
