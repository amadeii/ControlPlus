<?php

namespace App\Http\Controllers;

use App\Models\Deposito;
use App\Models\Funcionario;
use App\Models\Produto;
use App\Models\ProdutoUnico;
use App\Models\ReparoInterno;
use App\Models\ReparoInternoLinhaProduto;
use App\Models\TradeinInventoryItem;
use App\Services\ReparoInternoHistoricoService;
use App\Services\ReparoInternoEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReparoInternoController extends Controller
{
    public function __construct(private ReparoInternoEstoqueService $estoqueService)
    {
        $this->middleware('permission:reparo_interno_view', ['only' => ['index', 'show']]);
        $this->middleware('permission:reparo_interno_create', ['only' => ['create', 'store']]);
        $this->middleware('permission:reparo_interno_view|reparo_interno_create', ['only' => ['serialsDisponiveis']]);
        $this->middleware('permission:reparo_interno_edit', ['only' => [
            'update', 'storeLinha', 'destroyLinha', 'finalizar', 'cancelar', 'marcarEmAndamento',
        ]]);
    }

    public function index(Request $request)
    {
        $status = $request->get('status');

        $data = ReparoInterno::where('empresa_id', request()->empresa_id)
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['funcionario', 'tradeinInventoryItem', 'produto'])
            ->orderByDesc('id')
            ->paginate(__itensPagina())
            ->appends($request->query());

        $statusLabels = ReparoInterno::statuses();

        return view('reparo_interno.index', compact('data', 'status', 'statusLabels'));
    }

    public function create()
    {
        $funcionarios = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();
        $opcoesLocal = [];

        if (function_exists('__getLocaisAtivoUsuario')) {
            foreach (__getLocaisAtivoUsuario() as $loc) {
                $opcoesLocal[(int) $loc->id] = $loc->descricao;
            }
        }

        $tradeinOpcoes = $this->buildTradeinDisponiveisParaReparo();
        $depositosPecaOpcoes = $this->buildDepositosOpcoes();

        return view('reparo_interno.create', compact('funcionarios', 'opcoesLocal', 'tradeinOpcoes', 'depositosPecaOpcoes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'fonte' => 'required|in:tradein,estoque',
            'funcionario_id' => 'nullable|integer|exists:funcionarios,id',
            'observacao_tecnica' => 'nullable|string|max:5000',
            'local_id' => 'nullable|integer',
            'deposito_id' => 'nullable|integer',
            'tradein_inventory_item_id' => 'nullable|integer',
            'produto_id' => 'nullable|integer',
            'produto_unico_id' => 'nullable|integer',
        ]);

        $empresaId = (int) request()->empresa_id;

        if ($request->fonte === 'tradein') {
            $request->validate([
                'tradein_inventory_item_id' => 'required|integer|exists:tradein_inventory_items,id',
            ]);
        } else {
            $request->validate([
                'produto_id' => 'required|integer|exists:produtos,id',
            ]);
        }

        $tradeinId = null;
        $produtoId = null;
        $unicoId = null;

        if ($request->fonte === 'tradein') {
            $ti = TradeinInventoryItem::where('empresa_id', $empresaId)
                ->findOrFail((int) $request->tradein_inventory_item_id);
            $tradeinId = (int) $ti->id;

            if ($this->reparoAbertoParaTradein($empresaId, $tradeinId)) {
                session()->flash('flash_error', 'Já existe um reparo interno em aberto para este item de trade-in.');

                return redirect()->back()->withInput();
            }
        } else {
            $produto = Produto::where('empresa_id', $empresaId)->findOrFail((int) $request->produto_id);
            $produtoId = (int) $produto->id;

            if ($produto->tipo_unico) {
                $request->validate(['produto_unico_id' => 'required|integer|exists:produto_unicos,id']);

                $unico = ProdutoUnico::where('id', (int) $request->produto_unico_id)
                    ->where('produto_id', $produtoId)
                    ->where('tipo', 'entrada')
                    ->where('em_estoque', 1)
                    ->firstOrFail();

                $unicoId = (int) $unico->id;

                if ($this->reparoAbertoParaProdutoUnico($empresaId, $unicoId)) {
                    session()->flash('flash_error', 'Já existe um reparo interno em aberto para este serial.');

                    return redirect()->back()->withInput();
                }
            } else {
                $unicoId = null;

                if ($this->reparoAbertoParaProdutoLote($empresaId, $produtoId)) {
                    session()->flash('flash_error', 'Já existe um reparo interno em aberto para este produto de estoque (sem serial). Finalize ou cancele o reparo anterior.');

                    return redirect()->back()->withInput();
                }
            }
        }

        try {
            $rep = DB::transaction(function () use ($request, $empresaId, $tradeinId, $produtoId, $unicoId) {
                $last = ReparoInterno::where('empresa_id', $empresaId)->orderByDesc('codigo_sequencial')->first();
                $seq = $last ? ((int) $last->codigo_sequencial + 1) : 1;

                $rep = ReparoInterno::create([
                    'empresa_id' => $empresaId,
                    'codigo_sequencial' => $seq,
                    'status' => ReparoInterno::STATUS_ABERTO,
                    'tradein_inventory_item_id' => $tradeinId,
                    'produto_id' => $produtoId,
                    'produto_unico_id' => $unicoId,
                    'local_id' => $request->filled('local_id') ? (int) $request->local_id : null,
                    'deposito_id' => $request->filled('deposito_id') ? (int) $request->deposito_id : null,
                    'funcionario_id' => $request->filled('funcionario_id') ? (int) $request->funcionario_id : null,
                    'observacao_tecnica' => $request->observacao_tecnica,
                    'usuario_id' => Auth::id(),
                ]);

                ReparoInternoHistoricoService::registrar(
                    $rep,
                    'criacao',
                    'Reparo interno aberto — #' . $rep->codigo_sequencial
                );

                return $rep;
            });

            __createLog($empresaId, 'Reparo interno', 'cadastrar', '[reparo_interno] Aberto #' . $rep->codigo_sequencial);

            session()->flash('flash_success', 'Reparo interno criado com sucesso.');

            return redirect()->route('reparo-interno.show', $rep->id);
        } catch (\Throwable $e) {
            session()->flash('flash_error', 'Não foi possível criar: ' . $e->getMessage());

            return redirect()->back()->withInput();
        }
    }

    public function show($id)
    {
        $reparo = ReparoInterno::with([
            'linhas.produto',
            'funcionario',
            'tradeinInventoryItem.tradein',
            'produto',
            'produtoUnico',
            'eventos.user',
            'custoPecaLancamentos.user',
            'custoPecaLancamentos.peca',
            'usuario',
        ])->findOrFail($id);

        __validaObjetoEmpresa($reparo);

        $depositosPecaOpcoes = ['' => 'Padrão (local do reparo ou ativo)'];
        $mostrarSelectDeposito = false;

        $localIds = collect();
        if (function_exists('__getLocaisAtivoUsuario')) {
            $localIds = __getLocaisAtivoUsuario()->pluck('id');
        }

        $q = Deposito::where('empresa_id', request()->empresa_id)->with('localizacao')->orderBy('nome');
        if ($localIds->isNotEmpty()) {
            $q->whereIn('local_id', $localIds);
        }
        $lista = $q->get();
        if ($lista->isEmpty()) {
            $lista = Deposito::where('empresa_id', request()->empresa_id)->with('localizacao')->orderBy('nome')->get();
        }
        foreach ($lista as $d) {
            $depositosPecaOpcoes[(string) $d->id] = $d->nome . ($d->localizacao ? ' — ' . $d->localizacao->nome : '');
        }
        $mostrarSelectDeposito = count($depositosPecaOpcoes) > 1;

        return view('reparo_interno.show', compact('reparo', 'depositosPecaOpcoes', 'mostrarSelectDeposito'));
    }

    public function update(Request $request, $id)
    {
        $reparo = ReparoInterno::findOrFail($id);
        __validaObjetoEmpresa($reparo);

        if (!$reparo->permiteEditarConteudo()) {
            session()->flash('flash_error', 'Este reparo não pode mais ser alterado.');

            return redirect()->back();
        }

        $request->validate([
            'observacao_tecnica' => 'nullable|string|max:5000',
            'funcionario_id' => 'nullable|integer|exists:funcionarios,id',
        ]);

        $reparo->observacao_tecnica = $request->observacao_tecnica;
        $reparo->funcionario_id = $request->filled('funcionario_id') ? (int) $request->funcionario_id : null;
        $reparo->save();

        ReparoInternoHistoricoService::registrar($reparo, 'atualizacao', 'Observações ou técnico atualizados.');
        session()->flash('flash_success', 'Dados atualizados.');

        return redirect()->back();
    }

    public function storeLinha(Request $request)
    {
        $request->validate([
            'reparo_interno_id' => 'required|integer',
            'produto_id' => 'required|integer',
            'quantidade_produto' => 'required',
            'valor_produto' => 'nullable',
            'deposito_reparo_peca_id' => 'nullable|integer',
        ]);

        $rep = ReparoInterno::findOrFail((int) $request->reparo_interno_id);
        __validaObjetoEmpresa($rep);

        if (!$rep->permiteEditarConteudo()) {
            session()->flash('flash_error', 'Não é possível incluir peças neste status.');

            return redirect()->back();
        }

        try {
            DB::transaction(function () use ($request, $rep) {
                Produto::where('empresa_id', $rep->empresa_id)->findOrFail((int) $request->produto_id);

                $quantidade = __convert_value_bd($request->quantidade_produto);
                $valorUnit = $request->filled('valor_produto') ? __convert_value_bd($request->valor_produto) : 0;
                $subtotal = $quantidade * $valorUnit;

                $linha = ReparoInternoLinhaProduto::create([
                    'reparo_interno_id' => $rep->id,
                    'produto_id' => (int) $request->produto_id,
                    'quantidade' => $quantidade,
                    'valor' => $valorUnit,
                    'subtotal' => $subtotal,
                ]);

                $linha->load('produto');
                $depositoId = $request->filled('deposito_reparo_peca_id') ? (int) $request->deposito_reparo_peca_id : null;
                $this->estoqueService->aplicarBaixa($rep, $linha, $depositoId ?: null);

                ReparoInternoHistoricoService::registrar(
                    $rep,
                    'peca_incluida',
                    'Peça: ' . ($linha->produto ? $linha->produto->nome : $linha->produto_id) . ' — qtd ' . $quantidade
                );
            });

            session()->flash('flash_success', 'Peça registrada e estoque baixado quando aplicável.');
        } catch (\Throwable $e) {
            session()->flash('flash_error', $e->getMessage());
        }

        return redirect()->back();
    }

    public function destroyLinha($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $linha = ReparoInternoLinhaProduto::with('produto')->where('id', $id)->lockForUpdate()->firstOrFail();
                $rep = ReparoInterno::where('id', $linha->reparo_interno_id)->lockForUpdate()->firstOrFail();
                __validaObjetoEmpresa($rep);

                if (!$rep->permiteEditarConteudo()) {
                    throw new \RuntimeException('Não é possível remover peças neste status.');
                }

                $nome = $linha->produto ? $linha->produto->nome : '';
                $this->estoqueService->aplicarEstorno($rep, $linha);

                $linha->delete();

                ReparoInternoHistoricoService::registrar($rep, 'peca_removida', 'Removida peça: ' . $nome);
            });

            session()->flash('flash_success', 'Peça removida e estoque estornado quando aplicável.');
        } catch (\Throwable $e) {
            session()->flash('flash_error', $e->getMessage());
        }

        return redirect()->back();
    }

    public function marcarEmAndamento($id)
    {
        $reparo = ReparoInterno::findOrFail($id);
        __validaObjetoEmpresa($reparo);

        if ($reparo->status !== ReparoInterno::STATUS_ABERTO) {
            session()->flash('flash_warning', 'Somente reparos em aberto podem ir para em andamento.');

            return redirect()->back();
        }

        $reparo->status = ReparoInterno::STATUS_EM_ANDAMENTO;
        $reparo->save();

        ReparoInternoHistoricoService::registrar($reparo, 'status', 'Status: em andamento.');
        session()->flash('flash_success', 'Status atualizado.');

        return redirect()->back();
    }

    public function finalizar($id)
    {
        $reparo = ReparoInterno::findOrFail($id);
        __validaObjetoEmpresa($reparo);

        if ($reparo->isEncerrado()) {
            session()->flash('flash_warning', 'Reparo já encerrado.');

            return redirect()->back();
        }

        $reparo->status = ReparoInterno::STATUS_FINALIZADO;
        $reparo->finalizado_at = now();
        $reparo->usuario_finalizacao_id = Auth::id();
        $reparo->save();

        ReparoInternoHistoricoService::registrar($reparo, 'finalizado', 'Reparo finalizado.');
        __createLog((int) $reparo->empresa_id, 'Reparo interno', 'editar', '[reparo_interno] Finalizado #' . $reparo->codigo_sequencial);
        session()->flash('flash_success', 'Reparo finalizado.');

        return redirect()->back();
    }

    public function cancelar($id)
    {
        $reparo = ReparoInterno::with(['linhas.produto'])->findOrFail($id);
        __validaObjetoEmpresa($reparo);

        if ($reparo->isEncerrado()) {
            session()->flash('flash_warning', 'Reparo já encerrado.');

            return redirect()->back();
        }

        try {
            DB::transaction(function () use ($reparo) {
                foreach ($reparo->linhas as $linha) {
                    $this->estoqueService->aplicarEstorno($reparo, $linha);
                    ReparoInternoLinhaProduto::where('id', $linha->id)->delete();
                }

                $reparoFresh = ReparoInterno::where('id', $reparo->id)->lockForUpdate()->firstOrFail();
                $reparoFresh->status = ReparoInterno::STATUS_CANCELADO;
                $reparoFresh->cancelado_at = now();
                $reparoFresh->usuario_cancelamento_id = Auth::id();
                $reparoFresh->save();

                ReparoInternoHistoricoService::registrar($reparoFresh, 'cancelado', 'Reparo cancelado — estorno de peças aplicado.');
            });

            __createLog((int) $reparo->empresa_id, 'Reparo interno', 'excluir', '[reparo_interno] Cancelado #' . $reparo->codigo_sequencial);
            session()->flash('flash_success', 'Reparo cancelado; peças estornadas e custos revertidos.');
        } catch (\Throwable $e) {
            session()->flash('flash_error', $e->getMessage());
        }

        return redirect()->back();
    }

    public function serialsDisponiveis(Request $request)
    {
        $request->validate([
            'produto_id' => 'required|integer',
        ]);

        $empresaId = (int) request()->empresa_id;
        $produtoId = (int) $request->produto_id;

        $produto = Produto::where('empresa_id', $empresaId)->findOrFail($produtoId);

        if (!$produto->tipo_unico) {
            return response()->json([]);
        }

        $busy = ReparoInterno::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ReparoInterno::STATUS_FINALIZADO, ReparoInterno::STATUS_CANCELADO])
            ->whereNotNull('produto_unico_id')
            ->pluck('produto_unico_id');

        $q = ProdutoUnico::where('produto_id', $produtoId)
            ->where('tipo', 'entrada')
            ->where('em_estoque', 1);

        if ($busy->isNotEmpty()) {
            $q->whereNotIn('id', $busy->all());
        }

        $rows = $q->orderBy('id', 'desc')
            ->limit(500)
            ->get(['id', 'codigo']);

        return response()->json($rows->map(fn ($u) => [
            'id' => $u->id,
            'text' => $u->codigo,
        ]));
    }

    private function buildTradeinDisponiveisParaReparo(): array
    {
        $empresaId = (int) request()->empresa_id;
        $out = ['' => '— Selecione —'];

        $busy = ReparoInterno::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ReparoInterno::STATUS_FINALIZADO, ReparoInterno::STATUS_CANCELADO])
            ->whereNotNull('tradein_inventory_item_id')
            ->pluck('tradein_inventory_item_id');

        $items = TradeinInventoryItem::where('empresa_id', $empresaId)
            ->when($busy->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $busy->all()))
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        foreach ($items as $r) {
            $label = '#' . $r->id;
            if ($r->descricao_item) {
                $label .= ' — ' . $r->descricao_item;
            }
            if ($r->serial) {
                $label .= ' | S/N ' . $r->serial;
            }
            $label .= ' | R$ ' . number_format((float) ($r->valor ?? 0), 2, ',', '.');

            $out[(string) $r->id] = $label;
        }

        return $out;
    }

    private function buildDepositosOpcoes(): array
    {
        $opts = ['' => '— Padrão —'];
        $localIds = collect();
        if (function_exists('__getLocaisAtivoUsuario')) {
            $localIds = __getLocaisAtivoUsuario()->pluck('id');
        }
        $q = Deposito::where('empresa_id', request()->empresa_id)->with('localizacao')->orderBy('nome');
        if ($localIds->isNotEmpty()) {
            $q->whereIn('local_id', $localIds);
        }
        $lista = $q->get();
        if ($lista->isEmpty()) {
            $lista = Deposito::where('empresa_id', request()->empresa_id)->with('localizacao')->orderBy('nome')->get();
        }
        foreach ($lista as $d) {
            $opts[(string) $d->id] = $d->nome . ($d->localizacao ? ' — ' . $d->localizacao->nome : '');
        }

        return $opts;
    }

    private function reparoAbertoParaTradein(int $empresaId, int $tradeinInventoryItemId): bool
    {
        return ReparoInterno::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ReparoInterno::STATUS_FINALIZADO, ReparoInterno::STATUS_CANCELADO])
            ->where('tradein_inventory_item_id', $tradeinInventoryItemId)
            ->exists();
    }

    private function reparoAbertoParaProdutoUnico(int $empresaId, int $produtoUnicoId): bool
    {
        return ReparoInterno::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ReparoInterno::STATUS_FINALIZADO, ReparoInterno::STATUS_CANCELADO])
            ->where('produto_unico_id', $produtoUnicoId)
            ->exists();
    }

    private function reparoAbertoParaProdutoLote(int $empresaId, int $produtoId): bool
    {
        return ReparoInterno::where('empresa_id', $empresaId)
            ->whereNotIn('status', [ReparoInterno::STATUS_FINALIZADO, ReparoInterno::STATUS_CANCELADO])
            ->where('produto_id', $produtoId)
            ->whereNull('tradein_inventory_item_id')
            ->whereNull('produto_unico_id')
            ->exists();
    }
}
