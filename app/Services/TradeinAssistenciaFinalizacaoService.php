<?php

namespace App\Services;

use App\Models\AssistenciaOsPecaBaixa;
use App\Models\EstoqueStatusSaldo;
use App\Models\MovimentacaoProduto;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\ProdutoOs;
use App\Models\ProdutoUnico;
use App\Models\TradeinInventoryItem;
use App\Models\TradeinInventoryItemCustoPecaOsLancamento;
use App\Utils\QuantidadeUtil;
use App\Utils\StatusKeyUtil;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TradeinAssistenciaFinalizacaoService
{
    private const STATUS_EM_ASSISTENCIA = 'em_assistencia';

    public function __construct(
        private AssistenciaOsEstoqueService $assistenciaOsEstoqueService,
    ) {
    }

    public function aprovarParaVenda(TradeinInventoryItem $item, OrdemServico $ordem): void
    {
        DB::transaction(function () use ($item, $ordem): void {
            $ordem = OrdemServico::where('id', (int) $ordem->id)
                ->lockForUpdate()
                ->firstOrFail();

            $item = TradeinInventoryItem::where('id', (int) $item->id)
                ->where('empresa_id', (int) $ordem->empresa_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->validarContexto($item, $ordem);

            $pendencias = AssistenciaOsPecaBaixa::where('ordem_servico_id', (int) $ordem->id)
                ->where('tradein_inventory_item_id', (int) $item->id)
                ->whereIn('status', [
                    AssistenciaOsPecaBaixa::STATUS_PENDENTE,
                    AssistenciaOsPecaBaixa::STATUS_BAIXADO,
                ])
                ->lockForUpdate()
                ->orderBy('id')
                ->get();

            foreach ($pendencias as $pendencia) {
                if ($pendencia->status === AssistenciaOsPecaBaixa::STATUS_BAIXADO) {
                    continue;
                }

                $linha = ProdutoOs::where('id', (int) $pendencia->produto_os_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assistenciaOsEstoqueService->aplicarBaixa($ordem, $linha, $pendencia->deposito_id ? (int) $pendencia->deposito_id : null);

                $pendencia->movimentacao_produto_id = $this->movimentacaoConsumoId((int) $linha->id);
                $pendencia->custo_lancamento_id = $this->custoLancamentoId((int) $linha->id);
                $pendencia->status = AssistenciaOsPecaBaixa::STATUS_BAIXADO;
                $pendencia->aprovado_por_user_id = Auth::id();
                $pendencia->baixado_em = now();
                $pendencia->save();
            }

            $this->liberarAparelhoParaVenda($item, $ordem);

            $item->status = TradeinInventoryItem::STATUS_TRANSFERRED;
            $item->save();

            __createLog(
                (int) $ordem->empresa_id,
                'Trade-in / Assistência',
                'editar',
                '[tradein_assistencia_commit] OS #' . $ordem->codigo_sequencial
                    . ' — inventário #' . $item->id
                    . ' aprovado para venda'
            );
        });
    }

    private function validarContexto(TradeinInventoryItem $item, OrdemServico $ordem): void
    {
        if (!$ordem->isOsInterna() || empty($ordem->tradein_inventory_item_id)) {
            throw new \DomainException('Somente OS interna vinculada a Trade-In pode executar este commit.');
        }

        if ((int) $ordem->tradein_inventory_item_id !== (int) $item->id) {
            throw new \DomainException('A OS informada não pertence ao item de Trade-In selecionado.');
        }

        if ($ordem->estado !== 'ap') {
            throw new \DomainException('A OS deve estar aprovada para executar o commit administrativo.');
        }

        if (!in_array((string) $item->status, [
            self::STATUS_EM_ASSISTENCIA,
            TradeinInventoryItem::STATUS_TRANSFERRED,
        ], true)) {
            throw new \DomainException('O item de Trade-In não está em um status compatível com a aprovação para venda.');
        }
    }

    private function movimentacaoConsumoId(int $produtoOsId): ?int
    {
        return MovimentacaoProduto::where('tipo_transacao', AssistenciaOsEstoqueService::TIPO_CONSUMO)
            ->where('codigo_transacao', $produtoOsId)
            ->orderByDesc('id')
            ->value('id');
    }

    private function custoLancamentoId(int $produtoOsId): ?int
    {
        return TradeinInventoryItemCustoPecaOsLancamento::where('produto_os_id', $produtoOsId)
            ->orderByDesc('id')
            ->value('id');
    }

    private function liberarAparelhoParaVenda(TradeinInventoryItem $item, OrdemServico $ordem): void
    {
        if (!$item->produto_id) {
            return;
        }

        $produto = Produto::where('id', (int) $item->produto_id)
            ->where('empresa_id', (int) $item->empresa_id)
            ->lockForUpdate()
            ->first();

        if (!$produto) {
            return;
        }

        if ($produto->tipo_unico || !empty($item->serial)) {
            $this->ativarProdutoSerializado($item, $produto);
            return;
        }

        $this->ativarProdutoPorSaldo($item, $ordem);
    }

    private function ativarProdutoSerializado(TradeinInventoryItem $item, Produto $produto): void
    {
        $serial = trim((string) $item->serial);
        if ($serial === '') {
            throw new \DomainException('O item Trade-In serializado precisa de serial para ser liberado para venda.');
        }

        $produtoUnico = ProdutoUnico::where('produto_id', (int) $produto->id)
            ->where('codigo', $serial)
            ->where('tipo', 'entrada')
            ->where('em_estoque', 1)
            ->lockForUpdate()
            ->first();

        if (!$produtoUnico) {
            throw new \DomainException('Não foi possível localizar a unidade serializada do aparelho para ativação.');
        }

        $produtoUnico->status_key = StatusKeyUtil::DEFAULT_STATUS;
        $produtoUnico->save();
    }

    private function ativarProdutoPorSaldo(TradeinInventoryItem $item, OrdemServico $ordem): void
    {
        $queryBase = EstoqueStatusSaldo::where('empresa_id', (int) $item->empresa_id)
            ->where('produto_id', (int) $item->produto_id)
            ->where('status_key', '!=', StatusKeyUtil::DEFAULT_STATUS)
            ->where('quantidade', '>', 0)
            ->orderBy('id');

        $preferencial = null;
        if ($ordem->local_id) {
            $preferencial = (clone $queryBase)
                ->where('local_id', (int) $ordem->local_id)
                ->lockForUpdate()
                ->get();

            if ($preferencial->count() > 1) {
                throw new \DomainException('Há mais de uma reserva não-ATIVO para o aparelho neste local; ativação administrativa ambígua.');
            }
        }

        $candidatos = $preferencial && $preferencial->count() === 1
            ? $preferencial
            : (clone $queryBase)->lockForUpdate()->get();

        if ($candidatos->isEmpty()) {
            return;
        }

        if ($candidatos->count() > 1) {
            throw new \DomainException('Há mais de uma reserva não-ATIVO para o aparelho; ativação administrativa ambígua.');
        }

        /** @var EstoqueStatusSaldo $registro */
        $registro = $candidatos->first();
        $atualUnits = QuantidadeUtil::toUnits($registro->quantidade);
        $novoUnits = $atualUnits - 1;

        if ($novoUnits < 0) {
            throw new \DomainException('Saldo operacional inválido para liberar o aparelho para venda.');
        }

        if ($novoUnits === 0) {
            $registro->delete();
            return;
        }

        $registro->quantidade = QuantidadeUtil::fromUnits($novoUnits);
        $registro->save();
    }
}
