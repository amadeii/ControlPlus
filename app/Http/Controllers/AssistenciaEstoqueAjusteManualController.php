<?php

namespace App\Http\Controllers;

use App\Models\AssistenciaEstoqueAjusteManual;
use App\Models\Deposito;
use App\Services\AssistenciaEstoqueAjusteManualService;
use App\Services\AssistenciaOsEstoqueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AssistenciaEstoqueAjusteManualController extends Controller
{
    public function __construct(private AssistenciaEstoqueAjusteManualService $ajusteService)
    {
        $this->middleware('permission:assistencia_estoque_ajuste_view', ['only' => ['index', 'show']]);
        $this->middleware('permission:assistencia_estoque_ajuste_create', ['only' => ['create', 'store']]);
    }

    public function index()
    {
        $data = AssistenciaEstoqueAjusteManual::where('empresa_id', request()->empresa_id)
            ->with(['produto', 'user', 'deposito.localizacao'])
            ->orderByDesc('id')
            ->paginate(__itensPagina());

        return view('assistencia_estoque_ajuste.index', compact('data'));
    }

    public function create()
    {
        if (!AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) request()->empresa_id)) {
            abort(403, 'Fluxo disponível apenas com tipo de OS em Assistência técnica.');
        }

        $motivosOpcoes = AssistenciaEstoqueAjusteManual::motivosLabels();
        $depositosPecaOpcoes = $this->buildDepositosOpcoes();
        $idempotencyKey = (string) Str::uuid();
        $opcoesLocal = [];
        if (function_exists('__getLocaisAtivoUsuario')) {
            foreach (__getLocaisAtivoUsuario() as $loc) {
                $opcoesLocal[(int) $loc->id] = $loc->descricao;
            }
        }

        return view('assistencia_estoque_ajuste.create', compact('motivosOpcoes', 'depositosPecaOpcoes', 'opcoesLocal', 'idempotencyKey'));
    }

    public function store(Request $request)
    {
        if (!AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) request()->empresa_id)) {
            abort(403, 'Fluxo disponível apenas com tipo de OS em Assistência técnica.');
        }

        $empresaId = (int) request()->empresa_id;
        $motivos = array_keys(AssistenciaEstoqueAjusteManual::motivosLabels());
        $locaisAtivosIds = collect();
        if (function_exists('__getLocaisAtivoUsuario')) {
            $locaisAtivosIds = __getLocaisAtivoUsuario()
                ->filter(function ($local) use ($empresaId) {
                    return isset($local->id, $local->empresa_id)
                        && (int) $local->empresa_id === $empresaId;
                })
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                });
        }

        $validator = Validator::make($request->all(), [
            'produto_id' => [
                'required',
                'integer',
                Rule::exists('produtos', 'id')->where('empresa_id', $empresaId),
            ],
            'quantidade' => 'required|numeric|min:0.0001',
            'motivo' => 'required|in:' . implode(',', $motivos),
            'observacao' => 'required|string|min:3|max:5000',
            'deposito_id' => [
                'nullable',
                'integer',
                Rule::exists('depositos', 'id')->where('empresa_id', $empresaId),
            ],
            'local_id' => [
                'nullable',
                'integer',
                Rule::exists('localizacaos', 'id')
                    ->where('empresa_id', $empresaId)
                    ->where('status', 1),
            ],
            'produto_variacao_id' => [
                'nullable',
                'integer',
                Rule::exists('produto_variacaos', 'id')
                    ->where('produto_id', (int) $request->input('produto_id')),
            ],
            'idempotency_key' => 'required|uuid',
        ]);
        $validator->after(function ($validator) use ($request, $empresaId, $locaisAtivosIds) {
            $localId = $request->filled('local_id') ? (int) $request->input('local_id') : null;
            $depositoId = $request->filled('deposito_id') ? (int) $request->input('deposito_id') : null;

            if ($localId && $locaisAtivosIds->isNotEmpty() && !$locaisAtivosIds->contains($localId)) {
                $validator->errors()->add('local_id', 'Local inválido para o usuário logado.');
            }

            if (!$depositoId) {
                return;
            }

            $deposito = Deposito::where('id', $depositoId)
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->first();
            if (!$deposito) {
                $validator->errors()->add('deposito_id', 'Depósito inválido para esta empresa.');
                return;
            }

            $localIdDeposito = (int) $deposito->local_id;
            if ($localId && $localIdDeposito !== $localId) {
                $validator->errors()->add('deposito_id', 'Depósito incompatível com o local informado.');
            }

            if ($locaisAtivosIds->isNotEmpty() && !$locaisAtivosIds->contains($localIdDeposito)) {
                $validator->errors()->add('deposito_id', 'Depósito inválido para os locais ativos do usuário.');
            }
        });
        $validator->validate();

        $depositoId = $request->filled('deposito_id') ? (int) $request->deposito_id : null;
        $localId = $request->filled('local_id') ? (int) $request->local_id : null;

        try {
            $this->ajusteService->registrar(
                $empresaId,
                (int) Auth::id(),
                (int) $request->produto_id,
                $request->quantidade,
                (string) $request->motivo,
                (string) $request->observacao,
                $depositoId ?: null,
                $localId ?: null,
                $request->filled('produto_variacao_id') ? (int) $request->produto_variacao_id : null,
                (string) $request->idempotency_key
            );
        } catch (\Throwable $e) {
            session()->flash('flash_error', $e->getMessage());

            return redirect()->back()->withInput();
        }

        session()->flash('flash_success', 'Baixa manual registrada com sucesso.');

        return redirect()->route('assistencia-estoque-ajuste.index');
    }

    public function show(int $id)
    {
        $item = AssistenciaEstoqueAjusteManual::with(['produto', 'user', 'deposito.localizacao'])
            ->findOrFail($id);
        __validaObjetoEmpresa($item);

        return view('assistencia_estoque_ajuste.show', compact('item'));
    }

    /** @return array<string, string> */
    private function buildDepositosOpcoes(): array
    {
        $opts = ['' => '— Padrão (local ativo / depósito padrão) —'];
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
            $opts[(string) $d->id] = $d->nome . ($d->localizacao ? ' — ' . $d->localizacao->descricao : '');
        }

        return $opts;
    }
}
