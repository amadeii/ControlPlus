<?php

namespace App\Services;

use App\Models\Produto;
use App\Models\ReparoInterno;
use App\Models\ReparoInternoCustoPecaLancamento;
use App\Models\ReparoInternoLinhaProduto;
use App\Models\MovimentacaoProduto;
use App\Models\Tradein;
use App\Models\TradeinInventoryItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Atualiza custo do aparelho (trade-in ou produto de estoque) ao consumir peças no reparo interno.
 */
class ReparoInternoCustoAparelhoService
{
    public function registrarAposBaixa(ReparoInterno $reparo, ReparoInternoLinhaProduto $linha): ?ReparoInternoCustoPecaLancamento
    {
        if (!$this->deveProcessar($reparo, $linha)) {
            return null;
        }

        if (ReparoInternoCustoPecaLancamento::where('reparo_interno_linha_produto_id', (int) $linha->id)->exists()) {
            return null;
        }

        $consumoId = MovimentacaoProduto::where('tipo_transacao', ReparoInternoEstoqueService::TIPO_CONSUMO)
            ->where('codigo_transacao', (int) $linha->id)
            ->orderByDesc('id')
            ->value('id');

        if (!$consumoId) {
            return null;
        }

        $peca = Produto::find($linha->produto_id);
        if (!$peca) {
            return null;
        }

        $custoUnit = (float) ($peca->valor_compra ?? 0);
        $qty = (float) $linha->quantidade;
        $incremento = round($custoUnit * $qty, 4);

        if ($incremento <= 0) {
            return null;
        }

        return DB::transaction(function () use ($reparo, $linha, $consumoId, $peca, $custoUnit, $qty, $incremento) {
            if ($reparo->tradein_inventory_item_id) {
                return $this->registrarTradein($reparo, $linha, (int) $consumoId, $peca, $custoUnit, $qty, $incremento);
            }

            return $this->registrarProdutoEstoque($reparo, $linha, (int) $consumoId, $peca, $custoUnit, $qty, $incremento);
        });
    }

    public function reverterPorLinha(int $linhaId, int $empresaId): void
    {
        $lanc = ReparoInternoCustoPecaLancamento::where('reparo_interno_linha_produto_id', $linhaId)
            ->where('empresa_id', $empresaId)
            ->first();

        if (!$lanc) {
            return;
        }

        DB::transaction(function () use ($lanc) {
            $sub = round((float) $lanc->valor_custo_incremento, 4);
            $repId = (int) $lanc->reparo_interno_id;
            $codigo = ReparoInterno::where('id', $repId)->value('codigo_sequencial');

            if ($lanc->tradein_inventory_item_id) {
                $item = TradeinInventoryItem::where('id', $lanc->tradein_inventory_item_id)
                    ->where('empresa_id', $lanc->empresa_id)
                    ->lockForUpdate()
                    ->first();

                if ($item) {
                    $atual = $item->valor !== null ? round((float) $item->valor, 2) : 0.0;
                    $novo = max(0, round($atual - $sub, 2));
                    $item->valor = $novo;
                    $item->save();
                    $this->sincronizarValorCompraProdutoCatalogo($item);

                    __createLog(
                        (int) $lanc->empresa_id,
                        'Reparo interno / custo',
                        'excluir',
                        '[reparo_interno_custo_estorno] inventário #' . $item->id
                            . ' — reparo #' . ($codigo ?? $repId)
                            . ' — -' . number_format($sub, 4, ',', '.')
                    );
                }
            } elseif ($lanc->produto_dispositivo_id) {
                $device = Produto::where('id', (int) $lanc->produto_dispositivo_id)
                    ->where('empresa_id', $lanc->empresa_id)
                    ->lockForUpdate()
                    ->first();

                if ($device) {
                    $atual = $device->valor_compra !== null ? round((float) $device->valor_compra, 2) : 0.0;
                    $novo = max(0, round($atual - $sub, 2));
                    $device->valor_compra = $novo;
                    $device->save();

                    __createLog(
                        (int) $lanc->empresa_id,
                        'Reparo interno / custo',
                        'excluir',
                        '[reparo_interno_custo_estorno] produto #' . $device->id
                            . ' — reparo #' . ($codigo ?? $repId)
                            . ' — -' . number_format($sub, 4, ',', '.')
                    );
                }
            }

            $lanc->delete();
        });
    }

    private function registrarTradein(
        ReparoInterno $reparo,
        ReparoInternoLinhaProduto $linha,
        int $consumoId,
        Produto $peca,
        float $custoUnit,
        float $qty,
        float $incremento
    ): ReparoInternoCustoPecaLancamento {
        $item = TradeinInventoryItem::where('id', (int) $reparo->tradein_inventory_item_id)
            ->where('empresa_id', (int) $reparo->empresa_id)
            ->lockForUpdate()
            ->firstOrFail();

        $antes = $item->valor !== null ? round((float) $item->valor, 2) : 0.0;
        $depois = round($antes + $incremento, 2);

        $tradein = Tradein::where('id', $item->tradein_id)->where('empresa_id', $item->empresa_id)->first();
        $valorAvaliadoOrigem = $tradein && $tradein->valor_avaliado !== null
            ? round((float) $tradein->valor_avaliado, 2)
            : null;

        $row = ReparoInternoCustoPecaLancamento::create([
            'empresa_id' => (int) $reparo->empresa_id,
            'reparo_interno_id' => (int) $reparo->id,
            'reparo_interno_linha_produto_id' => (int) $linha->id,
            'tradein_inventory_item_id' => (int) $item->id,
            'produto_dispositivo_id' => null,
            'movimentacao_produto_id' => $consumoId,
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
            (int) $reparo->empresa_id,
            'Reparo interno / custo',
            'editar',
            '[reparo_interno_custo_tradein] #' . $reparo->codigo_sequencial
                . ' — inv #' . $item->id
                . ' — peça ' . $peca->nome
                . ' +' . number_format($incremento, 4, ',', '.')
        );

        return $row;
    }

    private function registrarProdutoEstoque(
        ReparoInterno $reparo,
        ReparoInternoLinhaProduto $linha,
        int $consumoId,
        Produto $peca,
        float $custoUnit,
        float $qty,
        float $incremento
    ): ReparoInternoCustoPecaLancamento {
        $device = Produto::where('id', (int) $reparo->produto_id)
            ->where('empresa_id', (int) $reparo->empresa_id)
            ->lockForUpdate()
            ->firstOrFail();

        $antes = $device->valor_compra !== null ? round((float) $device->valor_compra, 2) : 0.0;
        $depois = round($antes + $incremento, 2);

        $row = ReparoInternoCustoPecaLancamento::create([
            'empresa_id' => (int) $reparo->empresa_id,
            'reparo_interno_id' => (int) $reparo->id,
            'reparo_interno_linha_produto_id' => (int) $linha->id,
            'tradein_inventory_item_id' => null,
            'produto_dispositivo_id' => (int) $device->id,
            'movimentacao_produto_id' => $consumoId,
            'produto_peca_id' => (int) $peca->id,
            'quantidade_peca' => $qty,
            'valor_compra_unitario_peca' => $custoUnit,
            'valor_custo_incremento' => $incremento,
            'custo_aparelho_antes' => $antes,
            'custo_aparelho_depois' => $depois,
            'valor_avaliado_tradein_origem' => null,
            'user_id' => Auth::id(),
        ]);

        $device->valor_compra = $depois;
        $device->save();

        __createLog(
            (int) $reparo->empresa_id,
            'Reparo interno / custo',
            'editar',
            '[reparo_interno_custo_produto] #' . $reparo->codigo_sequencial
                . ' — aparelho (catálogo) #' . $device->id
                . ' — peça ' . $peca->nome
                . ' +' . number_format($incremento, 4, ',', '.')
        );

        return $row;
    }

    private function deveProcessar(ReparoInterno $reparo, ReparoInternoLinhaProduto $linha): bool
    {
        if (!$reparo->tradein_inventory_item_id && !$reparo->produto_id) {
            return false;
        }

        $peca = Produto::find($linha->produto_id);

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
