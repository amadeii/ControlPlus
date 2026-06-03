<?php

namespace App\Services;

use App\Models\MovimentacaoProduto;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\ProdutoOs;
use App\Models\Tradein;
use App\Models\TradeinInventoryItem;
use App\Models\TradeinInventoryItemCustoPecaOsLancamento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Mantém custo cumulativo do aparelho no estoque trade-in ao consumir peças na OS (assistência técnica com baixa de estoque).
 */
class TradeinAssistenciaPecaCustoService
{
    /** Após Movimentação os_consumo_peca criada para a linha de produto na OS. */
    public function registrarAposBaixaAssistenciaPeca(OrdemServico $ordem, ProdutoOs $produtoOs): ?TradeinInventoryItemCustoPecaOsLancamento
    {
        if (!$this->deveProcessar($ordem, $produtoOs)) {
            return null;
        }

        if (TradeinInventoryItemCustoPecaOsLancamento::where('produto_os_id', (int) $produtoOs->id)->exists()) {
            return null;
        }

        $consumoId = MovimentacaoProduto::where('tipo_transacao', AssistenciaOsEstoqueService::TIPO_CONSUMO)
            ->where('codigo_transacao', (int) $produtoOs->id)
            ->orderByDesc('id')
            ->value('id');

        if (!$consumoId) {
            return null;
        }

        $peca = Produto::find($produtoOs->produto_id);
        if (!$peca) {
            return null;
        }

        $custoUnit = (float) ($peca->valor_compra ?? 0);
        $qty = (float) $produtoOs->quantidade;
        $incremento = round($custoUnit * $qty, 4);

        if ($incremento <= 0) {
            return null;
        }

        return DB::transaction(function () use ($ordem, $produtoOs, $consumoId, $peca, $custoUnit, $qty, $incremento) {
            $item = TradeinInventoryItem::where('id', (int) $ordem->tradein_inventory_item_id)
                ->where('empresa_id', (int) $ordem->empresa_id)
                ->lockForUpdate()
                ->firstOrFail();

            $antes = $item->valor !== null ? round((float) $item->valor, 2) : 0.0;
            $depois = round($antes + $incremento, 2);

            $tradein = Tradein::where('id', $item->tradein_id)->where('empresa_id', $item->empresa_id)->first();
            $valorAvaliadoOrigem = $tradein && $tradein->valor_avaliado !== null
                ? round((float) $tradein->valor_avaliado, 2)
                : null;

            $row = TradeinInventoryItemCustoPecaOsLancamento::create([
                'empresa_id' => (int) $ordem->empresa_id,
                'tradein_inventory_item_id' => (int) $item->id,
                'ordem_servico_id' => (int) $ordem->id,
                'produto_os_id' => (int) $produtoOs->id,
                'movimentacao_produto_id' => (int) $consumoId,
                'produto_peca_id' => (int) $peca->id,
                'quantidade_peca' => $qty,
                'valor_compra_unitario_peca' => $custoUnit,
                'valor_custo_incremento' => $incremento,
                'custo_aparelho_antes' => $antes,
                'custo_aparelho_depois' => $depois,
                'valor_avaliado_tradein_origem' => $valorAvaliadoOrigem,
                'user_id' => Auth::id(),
            ]);

            $item->valor = $depois;
            $item->save();

            $this->sincronizarValorCompraProdutoCatalogo($item);

            __createLog(
                (int) $ordem->empresa_id,
                'Trade-in / Assistência',
                'editar',
                '[tradein_custo_peca_os] OS #' . $ordem->codigo_sequencial
                    . ' — inventário #' . $item->id
                    . ' — peça ' . $peca->nome
                    . ' — +' . number_format($incremento, 4, ',', '.')
                    . ' (antes R$ ' . number_format($antes, 2, ',', '.')
                    . ' → depois R$ ' . number_format($depois, 2, ',', '.') . ')'
            );

            return $row;
        });
    }

    public function reverterPorProdutoOs(int $produtoOsId, int $empresaId): void
    {
        $lanc = TradeinInventoryItemCustoPecaOsLancamento::where('produto_os_id', $produtoOsId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$lanc) {
            return;
        }

        DB::transaction(function () use ($lanc) {
            $item = TradeinInventoryItem::where('id', $lanc->tradein_inventory_item_id)
                ->where('empresa_id', $lanc->empresa_id)
                ->lockForUpdate()
                ->first();

            $osCod = OrdemServico::where('id', $lanc->ordem_servico_id)->value('codigo_sequencial');

            if ($item) {
                $atual = $item->valor !== null ? round((float) $item->valor, 2) : 0.0;
                $sub = round((float) $lanc->valor_custo_incremento, 4);
                $novo = max(0, round($atual - $sub, 2));
                $item->valor = $novo;
                $item->save();
                $this->sincronizarValorCompraProdutoCatalogo($item);

                __createLog(
                    (int) $lanc->empresa_id,
                    'Trade-in / Assistência',
                    'excluir',
                    '[tradein_custo_peca_os_estorno] inventário #' . $item->id
                        . ' — OS #' . ($osCod ?? $lanc->ordem_servico_id)
                        . ' — -' . number_format($sub, 4, ',', '.')
                        . ' (R$ ' . number_format($atual, 2, ',', '.')
                        . ' → R$ ' . number_format($novo, 2, ',', '.') . ')'
                );
            }

            $lanc->delete();
        });
    }

    private function deveProcessar(OrdemServico $ordem, ProdutoOs $produtoOs): bool
    {
        if (empty($ordem->tradein_inventory_item_id)) {
            return false;
        }

        if (!AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) $ordem->empresa_id)) {
            return false;
        }

        $peca = Produto::find($produtoOs->produto_id);

        return $peca && $peca->gerenciar_estoque;
    }

    private function sincronizarValorCompraProdutoCatalogo(TradeinInventoryItem $item): void
    {
        if (!$item->produto_id) {
            return;
        }

        $produto = Produto::where('id', (int) $item->produto_id)
            ->where('empresa_id', (int) $item->empresa_id)
            ->first();

        if (!$produto) {
            return;
        }

        $novoCusto = $item->valor !== null ? round((float) $item->valor, 2) : (float) $produto->valor_compra;
        $produto->valor_compra = $novoCusto;
        $produto->save();
    }
}
