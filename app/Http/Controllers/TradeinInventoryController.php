<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ConfigGeral;
use App\Models\OrdemServico;
use App\Models\Produto;
use App\Models\Tradein;
use App\Models\AssistenciaOsPecaBaixa;
use App\Models\TradeinInventoryItem;
use App\Models\TradeinInventoryItemCustoPecaOsLancamento;
use App\Services\TradeinAssistenciaFinalizacaoService;
use Illuminate\Http\Request;

class TradeinInventoryController extends Controller
{
    public function __construct(
        private TradeinAssistenciaFinalizacaoService $tradeinAssistenciaFinalizacaoService,
    ) {
        $this->middleware('permission:tradein_view', ['only' => ['index', 'transferRedirect', 'edit', 'update']]);
        $this->middleware('permission:tradein_edit', ['only' => ['enviarParaAssistencia', 'aprovarParaVenda']]);
    }

    public function index(Request $request)
    {
        $status = $request->get('status');

        $items = TradeinInventoryItem::with('tradein')
            ->where('empresa_id', $request->empresa_id)
            ->when($status, function ($query) use ($status) {
                return $query->where('status', $status);
            })
            ->orderBy('id', 'desc')
            ->paginate(__itensPagina());

        $ordensServicoPorTradein = $this->mapaOrdensServicoPorTradein($request, $items->getCollection()->pluck('id')->all());

        $clienteIds = $items->pluck('cliente_id')->filter()->unique()->values();
        $clientes = $clienteIds->isEmpty()
            ? collect()
            : Cliente::whereIn('id', $clienteIds)->pluck('razao_social', 'id');

        return view('tradein.inventory', compact('items', 'clientes', 'status', 'ordensServicoPorTradein'));
    }

    public function transferRedirect(Request $request, $id)
    {
        $item = TradeinInventoryItem::where('empresa_id', $request->empresa_id)->findOrFail($id);
        __validaObjetoEmpresa($item);
        $valorAtual = Tradein::where('empresa_id', $request->empresa_id)
            ->where('id', $item->tradein_id)
            ->value('valor_avaliado');
        if ($valorAtual !== null && (float) $item->valor !== (float) $valorAtual) {
            $item->valor = (float) $valorAtual;
            $item->save();
        }

        return redirect()->route('estoque.create', [
            'empresa_id'           => $request->empresa_id,
            'quantidade'           => 1,
            'tradein_inventory_id' => $item->id,
            'tradein_id'           => $item->tradein_id,
            'descricao_item'       => $item->descricao_item,
            'produto_id'           => $item->produto_id,
            'serial'               => $item->serial,
            'valor'                => $valorAtual !== null ? $valorAtual : $item->valor,
        ]);
    }

    public function edit(Request $request, $id)
    {
        $item = TradeinInventoryItem::where('empresa_id', $request->empresa_id)->findOrFail($id);
        __validaObjetoEmpresa($item);

        $produto = $item->produto_id ? Produto::find($item->produto_id) : null;

        $historicoCustoAssistenciaOs = TradeinInventoryItemCustoPecaOsLancamento::with(['ordemServico', 'user', 'peca'])
            ->where('tradein_inventory_item_id', $item->id)
            ->orderByDesc('id')
            ->get();

        $historicoPendenciasAssistenciaOs = AssistenciaOsPecaBaixa::with([
            'ordemServico',
            'produtoOs.produto',
            'aprovadoPor',
        ])
            ->where('tradein_inventory_item_id', $item->id)
            ->orderByDesc('id')
            ->get();

        return view('tradein.inventory_edit', compact(
            'item',
            'produto',
            'historicoCustoAssistenciaOs',
            'historicoPendenciasAssistenciaOs'
        ));
    }

    public function update(Request $request, $id)
    {
        $item = TradeinInventoryItem::where('empresa_id', $request->empresa_id)->findOrFail($id);
        __validaObjetoEmpresa($item);

        $request->validate([
            'produto_id'         => 'nullable|integer|exists:produtos,id',
            'serial'             => 'nullable|string|max:120',
            'descricao_item'     => 'nullable|string|max:255',
            'observacao_tecnica' => 'nullable|string|max:1000',
            'status'             => 'nullable|in:' . implode(',', [
                TradeinInventoryItem::STATUS_PENDING_TRANSFER,
                TradeinInventoryItem::STATUS_EM_ASSISTENCIA,
                TradeinInventoryItem::STATUS_TRANSFERRED,
            ]),
        ], [
            'produto_id.exists'  => 'Produto não encontrado no catálogo.',
        ]);

        $item->update([
            'produto_id'         => $request->filled('produto_id') ? (int)$request->produto_id : $item->produto_id,
            'serial'             => $request->filled('serial') ? trim($request->serial) : $item->serial,
            'descricao_item'     => $request->filled('descricao_item') ? trim($request->descricao_item) : $item->descricao_item,
            'observacao_tecnica' => $request->filled('observacao_tecnica') ? trim($request->observacao_tecnica) : $item->observacao_tecnica,
            'status'             => $request->filled('status') ? $request->status : $item->status,
        ]);

        session()->flash('flash_success', 'Item de inventário atualizado com sucesso!');
        return redirect()->route('tradein.inventory.index', ['empresa_id' => $request->empresa_id]);
    }

    public function enviarParaAssistencia(Request $request, $id)
    {
        $item = TradeinInventoryItem::where('empresa_id', $request->empresa_id)->findOrFail($id);
        __validaObjetoEmpresa($item);

        $osExistente = $this->ordemServicoInternaAtiva($request, $item->id);
        if ($osExistente) {
            if ($item->status !== TradeinInventoryItem::STATUS_EM_ASSISTENCIA) {
                $item->status = TradeinInventoryItem::STATUS_EM_ASSISTENCIA;
                $item->save();
            }

            session()->flash('flash_warning', 'Este item já possui OS interna em aberto. Redirecionado para a OS vinculada.');

            return redirect()->route('ordem-servico.show', $osExistente->id);
        }

        if ($item->status !== TradeinInventoryItem::STATUS_PENDING_TRANSFER) {
            session()->flash('flash_error', 'Somente itens aguardando transferência podem ser enviados para assistência.');

            return redirect()->route('tradein.inventory.index', ['empresa_id' => $request->empresa_id]);
        }

        $cfg = ConfigGeral::where('empresa_id', $request->empresa_id)->first();
        if (!$cfg || $cfg->tipo_ordem_servico !== 'assistencia técinica') {
            session()->flash('flash_error', 'Abertura de OS interna está indisponível para esta empresa.');

            return redirect()->route('tradein.inventory.index', ['empresa_id' => $request->empresa_id]);
        }

        session()->flash('flash_warning', 'Preencha a OS interna para concluir o envio do Trade-In à assistência.');

        return redirect()->route('ordem-servico.create', [
            'empresa_id' => $request->empresa_id,
            'escopo_ordem_servico' => OrdemServico::ESCOPO_INTERNA,
            'tradein_inventory_item_id' => $item->id,
        ]);
    }

    public function aprovarParaVenda(Request $request, $id)
    {
        $item = TradeinInventoryItem::where('empresa_id', $request->empresa_id)->findOrFail($id);
        __validaObjetoEmpresa($item);

        $ordem = $this->ordemServicoInternaAtiva($request, $item->id);
        if (!$ordem) {
            session()->flash('flash_error', 'Nenhuma OS interna vinculada foi encontrada para este item.');

            return redirect()->route('tradein.inventory.index', ['empresa_id' => $request->empresa_id]);
        }

        try {
            $this->tradeinAssistenciaFinalizacaoService->aprovarParaVenda($item, $ordem);
            session()->flash('flash_success', 'Item aprovado para venda e peças pendentes baixadas com sucesso.');
        } catch (\DomainException $e) {
            session()->flash('flash_error', $e->getMessage());
        } catch (\Throwable $e) {
            __createLog($request->empresa_id, 'Trade-in / Assistência', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Não foi possível concluir a aprovação pós-reparo.');
        }

        return redirect()->route('tradein.inventory.index', ['empresa_id' => $request->empresa_id]);
    }

    private function mapaOrdensServicoPorTradein(Request $request, array $tradeinIds): array
    {
        if (empty($tradeinIds)) {
            return [];
        }

        return OrdemServico::where('empresa_id', $request->empresa_id)
            ->where('escopo_ordem_servico', OrdemServico::ESCOPO_INTERNA)
            ->whereIn('tradein_inventory_item_id', $tradeinIds)
            ->whereIn('estado', ['pd', 'ap'])
            ->orderByDesc('id')
            ->get(['id', 'codigo_sequencial', 'estado', 'tradein_inventory_item_id'])
            ->unique('tradein_inventory_item_id')
            ->keyBy('tradein_inventory_item_id')
            ->all();
    }

    private function ordemServicoInternaAtiva(Request $request, int $tradeinInventoryItemId): ?OrdemServico
    {
        return OrdemServico::where('empresa_id', $request->empresa_id)
            ->where('escopo_ordem_servico', OrdemServico::ESCOPO_INTERNA)
            ->where('tradein_inventory_item_id', $tradeinInventoryItemId)
            ->whereIn('estado', ['pd', 'ap'])
            ->orderByDesc('id')
            ->first();
    }
}
