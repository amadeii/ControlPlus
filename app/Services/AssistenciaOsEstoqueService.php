<?php

namespace App\Services;

use App\Models\ConfigGeral;
use App\Models\Deposito;
use App\Models\Estoque;
use App\Models\MovimentacaoProduto;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\ProdutoOs;
use App\Utils\EstoqueUtil;
use App\Utils\VariacaoQueryUtil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssistenciaOsEstoqueService
{
    public const TIPO_CONSUMO = 'os_consumo_peca';

    public const TIPO_ESTORNO = 'os_estorno_peca';

    public function __construct(private EstoqueUtil $estoqueUtil)
    {
    }

    public static function integraEstoqueParaEmpresa(?int $empresaId): bool
    {
        if (!$empresaId) {
            return false;
        }

        $cfg = ConfigGeral::where('empresa_id', $empresaId)->first();

        return $cfg && $cfg->tipo_ordem_servico === 'assistencia técinica';
    }

    /**
     * Baixa de estoque ao incluir linha de peça na OS (MVP: ao confirmar inclusão).
     *
     * @throws \Throwable
     */
    public function aplicarBaixa(OrdemServico $ordem, ProdutoOs $linha, ?int $depositoIdSolicitado = null): void
    {
        DB::transaction(function () use ($ordem, $linha, $depositoIdSolicitado): void {
            $linha = ProdutoOs::where('id', (int) $linha->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ordem = OrdemServico::where('id', (int) $ordem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $linha->ordem_servico_id !== (int) $ordem->id) {
                throw new \Exception('Linha de peça não pertence à OS informada.');
            }

            $produto = Produto::where('id', $linha->produto_id)
                ->where('empresa_id', (int) $ordem->empresa_id)
                ->lockForUpdate()
                ->first();

            if (!$produto || !$produto->gerenciar_estoque) {
                return;
            }

            if ($this->movimentacaoExiste(self::TIPO_CONSUMO, (int) $linha->id)) {
                return;
            }

            [$localId, $depositoId] = $this->resolveContextoBaixa($ordem, $depositoIdSolicitado);

            $quantidade = $linha->quantidade;
            $variacaoId = null;

            $this->lockEstoqueRegistro((int) $linha->produto_id, $variacaoId, (int) $localId, $depositoId);

            $this->estoqueUtil->reduzEstoque(
                (int) $linha->produto_id,
                $quantidade,
                $variacaoId,
                (int) $localId,
                $depositoId
            );

            $codigoTransacao = (int) $linha->id;
            $this->estoqueUtil->movimentacaoProduto(
                (int) $linha->produto_id,
                $quantidade,
                'reducao',
                $codigoTransacao,
                self::TIPO_CONSUMO,
                Auth::id(),
                $variacaoId,
                (int) $localId,
                $depositoId
            );

            __createLog(
                (int) $ordem->empresa_id,
                'Assistência / OS',
                'cadastrar',
                '[os_peca_incluida] OS #' . $ordem->codigo_sequencial . ' — ' . $produto->nome . ' qtd ' . $quantidade . ' (' . self::TIPO_CONSUMO . ')'
            );

            app(TradeinAssistenciaPecaCustoService::class)->registrarAposBaixaAssistenciaPeca($ordem, $linha);
        });
    }

    /**
     * Estorno simétrico quando a linha é removida ou a OS é excluída.
     *
     * @throws \Throwable
     */
    public function aplicarEstorno(OrdemServico $ordem, ProdutoOs $linha): void
    {
        DB::transaction(function () use ($ordem, $linha): void {
            $linha = ProdutoOs::where('id', (int) $linha->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ordem = OrdemServico::where('id', (int) $ordem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $linha->ordem_servico_id !== (int) $ordem->id) {
                throw new \Exception('Linha de peça não pertence à OS informada.');
            }

            $produto = Produto::where('id', $linha->produto_id)
                ->where('empresa_id', (int) $ordem->empresa_id)
                ->lockForUpdate()
                ->first();

            if (!$produto || !$produto->gerenciar_estoque) {
                return;
            }

            $consumo = MovimentacaoProduto::where('tipo_transacao', self::TIPO_CONSUMO)
                ->where('codigo_transacao', (int) $linha->id)
                ->lockForUpdate()
                ->first();

            if (!$consumo) {
                return;
            }

            if ($this->movimentacaoExiste(self::TIPO_ESTORNO, (int) $linha->id)) {
                return;
            }

            [$localId, $depositoId] = $this->resolveContextoEstorno($ordem, $consumo);

            $produtoId = (int) $consumo->produto_id;
            $quantidade = $consumo->quantidade;
            $variacaoId = $consumo->produto_variacao_id;

            $this->lockEstoqueRegistro($produtoId, $variacaoId, (int) $localId, $depositoId);

            $this->estoqueUtil->incrementaEstoque(
                $produtoId,
                $quantidade,
                $variacaoId,
                (int) $localId,
                $depositoId
            );

            $this->estoqueUtil->movimentacaoProduto(
                $produtoId,
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
                (int) $ordem->empresa_id,
                'Assistência / OS',
                'excluir',
                '[os_peca_removida] OS #' . $ordem->codigo_sequencial . ' — estorno ' . $produto->nome . ' qtd ' . $quantidade . ' (' . self::TIPO_ESTORNO . ')'
            );

            app(TradeinAssistenciaPecaCustoService::class)->reverterPorProdutoOs((int) $linha->id, (int) $ordem->empresa_id);
        });
    }

    private function movimentacaoExiste(string $tipoTransacao, int $codigoTransacao): bool
    {
        return MovimentacaoProduto::where('tipo_transacao', $tipoTransacao)
            ->where('codigo_transacao', $codigoTransacao)
            ->lockForUpdate()
            ->first() !== null;
    }

    private function resolveContextoBaixa(OrdemServico $ordem, ?int $depositoIdSolicitado): array
    {
        if ($depositoIdSolicitado) {
            $deposito = Deposito::where('id', $depositoIdSolicitado)
                ->where('empresa_id', (int) $ordem->empresa_id)
                ->lockForUpdate()
                ->first();

            if (!$deposito) {
                throw new \Exception('Depósito inválido para esta empresa.');
            }

            if (!$deposito->local_id) {
                throw new \Exception('Defina o local na OS ou um local ativo para registrar a baixa de estoque.');
            }

            return [(int) $deposito->local_id, (int) $deposito->id];
        }

        $localId = $this->resolveLocalPadrao(
            $ordem,
            'Defina o local na OS ou um local ativo para registrar a baixa de estoque.'
        );
        $deposito = Deposito::ensureDefaultForLocalId($localId);

        if (!$deposito) {
            throw new \Exception('Depósito padrão não definido para o local de estoque informado.');
        }

        return [(int) $deposito->local_id, (int) $deposito->id];
    }

    private function resolveContextoEstorno(OrdemServico $ordem, MovimentacaoProduto $consumo): array
    {
        $depositoId = $consumo->deposito_id ? (int) $consumo->deposito_id : null;
        $localId = Deposito::resolveLocalIdByDepositoId($depositoId);

        if ($localId) {
            return [(int) $localId, $depositoId];
        }

        $localId = $this->resolveLocalPadrao(
            $ordem,
            'Não foi possível resolver o local para estornar o estoque da peça.'
        );
        $deposito = Deposito::ensureDefaultForLocalId($localId);

        if (!$deposito) {
            throw new \Exception('Não foi possível resolver o local para estornar o estoque da peça.');
        }

        return [(int) $deposito->local_id, (int) $deposito->id];
    }

    private function resolveLocalPadrao(OrdemServico $ordem, string $mensagemErro): int
    {
        $localId = $ordem->local_id;
        if (!$localId && function_exists('__getLocalAtivo')) {
            $localAtivo = __getLocalAtivo();
            $localId = $localAtivo->id ?? null;
        }

        if (!$localId) {
            throw new \Exception($mensagemErro);
        }

        return (int) $localId;
    }

    private function lockEstoqueRegistro(int $produtoId, ?int $variacaoId, int $localId, ?int $depositoId): void
    {
        $query = Estoque::where('produto_id', $produtoId);
        $query = VariacaoQueryUtil::apply($query, $variacaoId);

        if ($depositoId) {
            $query->where(function ($q) use ($depositoId, $localId): void {
                $q->where('deposito_id', $depositoId)
                    ->orWhere(function ($legacy) use ($localId): void {
                        $legacy->whereNull('deposito_id')
                            ->where('local_id', $localId);
                    });
            });
        } else {
            $query->where('local_id', $localId);
        }

        $query->lockForUpdate()->get();
    }
}
