<?php

namespace App\Http\Controllers;

use App\Models\Cidade;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\FuncionarioOs;
use App\Models\OrdemServico;
use App\Models\OticaOs;
use App\Models\FaturaOrdemServico;
use App\Models\MedicaoReceitaOs;
use App\Models\Produto;
use App\Models\ProdutoOs;
use App\Models\FormatoArmacaoOtica;
use App\Models\RelatorioOs;
use App\Models\ConfigGeral;
use App\Models\Deposito;
use App\Models\ServicoOs;
use App\Models\Servico;
use App\Models\Funcionario;
use App\Models\MetaResultado;
use App\Models\NaturezaOperacao;
use App\Models\ContaReceber;
use App\Models\Nfe;
use App\Models\TratamentoOtica;
use App\Models\Convenio;
use App\Models\Veiculo;
use App\Models\TipoArmacao;
use App\Models\Transportadora;
use Illuminate\Http\Request;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Psy\CodeCleaner\ReturnTypePass;
use Dompdf\Dompdf;
use App\Utils\UploadUtil;
use Illuminate\Support\Str;
use App\Models\OrdemServicoDocumento;
use App\Services\AssistenciaOsAberturaService;
use App\Services\AssistenciaOsControleTecnicoService;
use App\Services\AssistenciaOsEstoqueService;
use App\Services\AssistenciaOsFinalizacaoService;
use App\Services\AssistenciaOsPecaBaixaPendenteService;
use App\Services\OrdemServicoDocumentoService;
use App\Services\OrdemServicoAuditoriaAlteracaoLogger;
use App\Models\TradeinInventoryItem;
use App\Models\TradeinInventoryItemCustoPecaOsLancamento;
use App\Models\ProdutoUnico;
use App\Models\AssistenciaOsPecaBaixa;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;

class OrdemServicoController extends Controller
{

    protected $util;

    public function __construct(
        UploadUtil $util,
        protected AssistenciaOsEstoqueService $assistenciaOsEstoque,
        protected AssistenciaOsPecaBaixaPendenteService $assistenciaOsPecaBaixaPendenteService,
        protected AssistenciaOsControleTecnicoService $assistenciaOsControleTecnico,
        protected AssistenciaOsAberturaService $assistenciaOsAberturaService,
        protected AssistenciaOsFinalizacaoService $assistenciaOsFinalizacaoService,
        protected OrdemServicoDocumentoService $ordemServicoDocumentoService,
    ) {
        $this->util = $util;
        $this->middleware('permission:ordem_servico_create|ordem_servico_interna_create', ['only' => ['create', 'store']]);
        $this->middleware('permission:ordem_servico_edit|ordem_servico_interna_edit', ['only' => [
            'edit', 'update', 'alterarEstado', 'updateEstado',
            'assistenciaAtualizarControle', 'assistenciaAlternarChecklist',
        ]]);
        $this->middleware('permission:ordem_servico_view|ordem_servico_interna_view', [
            'only' => ['show', 'index', 'imprimir', 'duplicar', 'assistenciaFilaTecnica', 'assistenciaPainel', 'downloadDocumento'],
        ]);
        $this->middleware('permission:ordem_servico_delete', ['only' => ['destroy', 'destroySelecet']]);
        $this->middleware('permission:ordem_servico_create|ordem_servico_interna_create|ordem_servico_edit|ordem_servico_interna_edit', [
            'only' => ['aparelhoInternoSeriais'],
        ]);
    }

    public function index(Request $request)
    {
        $data = $this->paginateOrdensServicoLista($request);
        $convenios = Convenio::where('empresa_id', request()->empresa_id)
            ->where('status', 1)->get();

        $veiculos = Veiculo::where('empresa_id', request()->empresa_id)
            ->where('status', 1)->get();

        $configGeral = ConfigGeral::where('empresa_id', request()->empresa_id)->first();
        $funcionariosFiltroAssistencia = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();

        return view('ordem_servico.index', compact('data', 'convenios', 'veiculos', 'configGeral', 'funcionariosFiltroAssistencia'));
    }

    private function queryOrdensServicoFiltradas(Request $request)
    {
        $cliente_id = $request->get('cliente_id');
        $start_date = $request->get('start_date');
        $end_date = $request->get('end_date');
        $codigo = $request->get('codigo');
        $convenio_id = $request->get('convenio_id');
        $situacao_entrega = $request->get('situacao_entrega');
        $adiantamento = $request->get('adiantamento');
        $veiculo_id = $request->get('veiculo_id');
        $estado = $request->get('estado');
        $equipamento = $request->get('equipamento');
        $numero_serie = $request->get('numero_serie');
        $tecnico_responsavel_id = $request->get('tecnico_responsavel_id');
        $assistencia_fase_tecnica = $request->get('assistencia_fase_tecnica');

        return OrdemServico::where('empresa_id', request()->empresa_id)
            ->select('ordem_servicos.*')
            ->tap(function ($query) {
                if (!Gate::allows('ordem_servico_interna_view')) {
                    $query->where(function ($q) {
                        $q->whereNull('ordem_servicos.escopo_ordem_servico')
                            ->orWhere('ordem_servicos.escopo_ordem_servico', OrdemServico::ESCOPO_CLIENTE);
                    });
                }
            })
            ->when(Gate::allows('ordem_servico_interna_view') && $request->filled('escopo_os'), function ($query) use ($request) {
                $v = (string) $request->escopo_os;
                if (\in_array($v, [OrdemServico::ESCOPO_CLIENTE, OrdemServico::ESCOPO_INTERNA], true)) {
                    return $query->where('ordem_servicos.escopo_ordem_servico', $v);
                }

                return $query;
            })
            ->when(!empty($cliente_id), function ($query) use ($cliente_id) {
                return $query->where('cliente_id', $cliente_id);
            })
            ->when(!empty($start_date), function ($query) use ($start_date) {
                return $query->whereDate('ordem_servicos.data_inicio', '>=', $start_date);
            })
            ->when(!empty($end_date), function ($query) use ($end_date) {
                return $query->whereDate('ordem_servicos.data_inicio', '<=', $end_date);
            })
            ->when(!empty($codigo), function ($query) use ($codigo) {
                return $query->where('ordem_servicos.codigo_sequencial', $codigo);
            })
            ->when(!empty($estado), function ($query) use ($estado) {
                return $query->where('ordem_servicos.estado', $estado);
            })
            ->when(!empty($tecnico_responsavel_id), function ($query) use ($tecnico_responsavel_id) {
                return $query->where('ordem_servicos.tecnico_responsavel_id', (int) $tecnico_responsavel_id);
            })
            ->when(!empty($assistencia_fase_tecnica), function ($query) use ($assistencia_fase_tecnica) {
                return $query->where('ordem_servicos.assistencia_fase_tecnica', (string) $assistencia_fase_tecnica);
            })
            ->when(!empty($veiculo_id), function ($query) use ($veiculo_id) {
                return $query->where('ordem_servicos.veiculo_id', $veiculo_id);
            })
            ->when($request->filled('equipamento'), function ($query) use ($equipamento) {
                $term = trim((string) $equipamento);

                return $query->where(function ($q) use ($term) {
                    $q->where('ordem_servicos.equipamento', 'like', '%' . $term . '%')
                        ->orWhere('ordem_servicos.marca_equipamento', 'like', '%' . $term . '%')
                        ->orWhere('ordem_servicos.modelo_equipamento', 'like', '%' . $term . '%')
                        ->orWhereHas('itens', function ($qi) use ($term) {
                            $qi->where('produto_os.descricao_livre', 'like', '%' . $term . '%')
                                ->orWhere('produto_os.marca_livre', 'like', '%' . $term . '%')
                                ->orWhere('produto_os.modelo_livre', 'like', '%' . $term . '%');
                        });
                });
            })
            ->when($request->filled('numero_serie'), function ($query) use ($numero_serie) {
                $term = trim((string) $numero_serie);

                return $query->where(function ($q) use ($term) {
                    $q->where('ordem_servicos.numero_serie', 'like', '%' . $term . '%')
                        ->orWhereHas('itens', function ($qi) use ($term) {
                            $qi->where('produto_os.imei_serial_livre', 'like', '%' . $term . '%');
                        });
                });
            })
            ->when(!empty($convenio_id), function ($query) use ($convenio_id) {
                return $query->join('otica_os', 'otica_os.ordem_servico_id', '=', 'ordem_servicos.id')
                    ->where('otica_os.convenio_id', $convenio_id);
            })
            ->when(!empty($situacao_entrega), function ($query) use ($situacao_entrega) {
                if ($situacao_entrega == 1) {
                    return $query->where('ordem_servicos.data_entrega', '!=', '');
                }

                return $query->where('ordem_servicos.data_entrega', null);
            })
            ->when(!empty($adiantamento), function ($query) use ($adiantamento) {
                if ($adiantamento == 1) {
                    return $query->where('ordem_servicos.adiantamento', '>', 0);
                }

                return $query->where('ordem_servicos.adiantamento', 0);
            });
    }

    private function paginateOrdensServicoLista(Request $request): LengthAwarePaginator
    {
        return $this->queryOrdensServicoFiltradas($request)
            ->with(['cliente', 'veiculo', 'funcionario', 'tecnicoResponsavel'])
            ->orderBy('id', 'desc')
            ->paginate(__itensPagina())
            ->appends($request->query());
    }

    public function create()
    {

        if (!__isCaixaAberto()) {
            session()->flash("flash_warning", "Abrir caixa antes de continuar!");
            return redirect()->route('caixa.create');
        }
        $hoje = date('d/m/Y');
        $funcionario = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();
        $clientes = Cliente::where('empresa_id', request()->empresa_id)->first();
        $usuario = Auth::user();

        $servicos = Servico::where('empresa_id', request()->empresa_id)->first();

        if ($funcionario->isEmpty()) {
            session()->flash('flash_warning', 'Cadastrar um funcionario antes de continuar!');
            return redirect()->route('funcionarios.create');
        }
        if ($clientes == null) {
            session()->flash('flash_warning', 'Cadastrar um cliente antes de continuar!');
            return redirect()->route('clientes.create');
        }
        // dd($clientes);
        if ($servicos == null) {
            session()->flash('flash_warning', 'Cadastrar um serviço antes de continuar!');
            return redirect()->route('servicos.create');
        }


        $convenios = Convenio::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $formatosArmacao = FormatoArmacaoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tiposArmacao = TipoArmacao::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tratamentos = TratamentoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        $veiculos = Veiculo::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        $configGeral = ConfigGeral::where('empresa_id', request()->empresa_id)->first();

        $tradeinOpcoesInventarioOs = $this->buildTradeinOpcoesInventarioOs();

        return view('ordem_servico.create', compact('hoje', 'funcionario', 'usuario', 'servicos', 'convenios', 
            'formatosArmacao', 'tiposArmacao', 'tratamentos', 'veiculos', 'configGeral', 'tradeinOpcoesInventarioOs'));
    }

    public function edit($id)
    {
        $funcionario = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();
        $clientes = Cliente::where('empresa_id', request()->empresa_id)->first();
        $usuario = Auth::user();

        $servicos = Servico::where('empresa_id', request()->empresa_id)->first();

        $item = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($item);

        $this->authorizePorEscopoOrdemServico($item, 'ordem_servico_edit', 'ordem_servico_interna_edit');
        
        $convenios = Convenio::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $formatosArmacao = FormatoArmacaoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tiposArmacao = TipoArmacao::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tratamentos = TratamentoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        if($item->oticaOs){
            $item->oticaOs->tratamentos = json_decode($item->oticaOs->tratamentos ? $item->oticaOs->tratamentos : '[]');
        }

        $veiculos = Veiculo::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        $configGeral = ConfigGeral::where('empresa_id', request()->empresa_id)->first();

        $tradeinOpcoesInventarioOs = $this->buildTradeinOpcoesInventarioOs();

        return view('ordem_servico.edit', compact('funcionario', 'usuario', 'servicos', 'item', 'convenios', 
            'formatosArmacao', 'tiposArmacao', 'tratamentos', 'veiculos', 'configGeral', 'tradeinOpcoesInventarioOs'));
    }

    public function duplicar($id)
    {
        $funcionario = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();
        $clientes = Cliente::where('empresa_id', request()->empresa_id)->first();
        $usuario = Auth::user();

        $servicos = Servico::where('empresa_id', request()->empresa_id)->first();

        $item = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($item);

        $this->authorizePorEscopoOrdemServico($item, 'ordem_servico_view', 'ordem_servico_interna_view');
        
        $convenios = Convenio::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $formatosArmacao = FormatoArmacaoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tiposArmacao = TipoArmacao::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();
        $tratamentos = TratamentoOtica::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        if($item->oticaOs){
            $item->oticaOs->tratamentos = json_decode($item->oticaOs->tratamentos ? $item->oticaOs->tratamentos : '[]');
        }

        $veiculos = Veiculo::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        $configGeral = ConfigGeral::where('empresa_id', request()->empresa_id)->first();

        $tradeinOpcoesInventarioOs = $this->buildTradeinOpcoesInventarioOs();

        return view('ordem_servico.duplicar', compact('funcionario', 'usuario', 'servicos', 'item', 'convenios', 
            'formatosArmacao', 'tiposArmacao', 'tratamentos', 'veiculos', 'configGeral', 'tradeinOpcoesInventarioOs'));
    }

    public function store(Request $request)
    {
        $cfg = ConfigGeral::where('empresa_id', $request->empresa_id)->first();
        $ordemCriadaParaCompensar = null;
        try {
            $escopoNorm = OrdemServico::ESCOPO_CLIENTE;
            if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
                $rawEsc = $request->input('escopo_ordem_servico', OrdemServico::ESCOPO_CLIENTE);
                $escopoNorm = $rawEsc === OrdemServico::ESCOPO_INTERNA
                    ? OrdemServico::ESCOPO_INTERNA
                    : OrdemServico::ESCOPO_CLIENTE;
            }

            $request->merge([
                'escopo_ordem_servico' => $escopoNorm,
            ]);

            if ($escopoNorm === OrdemServico::ESCOPO_INTERNA) {
                Gate::authorize('ordem_servico_interna_create');
                $request->merge(['cliente_id' => null]);
            } else {
                Gate::authorize('ordem_servico_create');
            }

            $this->validarAberturaOsClienteExigeCliente($request, $escopoNorm);

            $this->_validate($request);

            $lastItem = OrdemServico::where('empresa_id', $request->empresa_id)
                ->orderBy('codigo_sequencial', 'desc')->first();
            $codigo_sequencial = 1;
            if ($lastItem != null) {
                $codigo_sequencial = $lastItem->codigo_sequencial + 1;
            }

            $tradeinInvId = null;
            $produtoAparelhoId = null;
            $produtoAparelhoUniId = null;

            if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
                if ($escopoNorm === OrdemServico::ESCOPO_INTERNA) {
                    $this->validarEPrepararCamposOsInterna($request);
                    $tradeinInvId = $request->filled('tradein_inventory_item_id') ? (int) $request->tradein_inventory_item_id : null;
                    $produtoAparelhoId = $request->filled('produto_aparelho_id') ? (int) $request->produto_aparelho_id : null;
                    $produtoAparelhoUniId = $request->filled('produto_aparelho_unico_id') ? (int) $request->produto_aparelho_unico_id : null;
                } else {
                    $request->validate([
                        'cliente_id' => 'required|integer|exists:clientes,id',
                    ]);
                    Cliente::where('empresa_id', $request->empresa_id)->where('id', $request->cliente_id)->firstOrFail();

                    $tradeinInvId = $request->filled('tradein_inventory_item_id')
                        ? (int) $request->tradein_inventory_item_id
                        : null;
                    $produtoAparelhoId = null;
                    $produtoAparelhoUniId = null;
                }
            } else {
                $request->validate([
                    'cliente_id' => 'required|integer|exists:clientes,id',
                ]);
                Cliente::where('empresa_id', $request->empresa_id)->where('id', $request->cliente_id)->firstOrFail();
            }

            if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
                $this->validarAberturaAssistenciaMinima($request, $escopoNorm);
            }

            $createData = [
                'descricao' => $request->descricao ?? '',
                'usuario_id' => get_id_user(),
                'cliente_id' => $request->cliente_id,
                'empresa_id' => $request->empresa_id,
                'codigo_sequencial' => $codigo_sequencial,
                'data_inicio' => $request->data_inicio,
                'data_entrega' => $request->data_entrega,
                'funcionario_id' => $request->funcionario_id,
                'veiculo_id' => $request->veiculo_id ?? null,
                'hash_link' => Str::random(30),
                'tipo_servico' => $request->tipo_servico ?? null,
                'diagnostico_cliente' => $request->diagnostico_cliente ?? '',
                'equipamento' => $request->equipamento ?? null,
                'numero_serie' => $request->numero_serie ?? null,
                'senha_aparelho' => $request->senha_aparelho ?? null,
                'acessorios' => $request->acessorios ?? null,
                'cor' => $request->cor ?? null,
                'tradein_inventory_item_id' => $tradeinInvId,
                'escopo_ordem_servico' => $cfg && $cfg->tipo_ordem_servico === 'assistencia técinica'
                    ? $escopoNorm
                    : OrdemServico::ESCOPO_CLIENTE,
                'produto_aparelho_id' => $produtoAparelhoId,
                'produto_aparelho_unico_id' => $produtoAparelhoUniId,
            ];

            if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
                $faseKeys = array_keys(OrdemServico::assistenciaFasesTecnicas());
                $faseIn = (string) $request->input('assistencia_fase_tecnica', '');
                $createData['marca_equipamento'] = $request->marca_equipamento ?? null;
                $createData['modelo_equipamento'] = $request->modelo_equipamento ?? null;
                $createData['tecnico_responsavel_id'] = $request->filled('tecnico_responsavel_id')
                    ? (int) $request->tecnico_responsavel_id
                    : null;
                $createData['data_previsao_entrega'] = $request->filled('data_previsao_entrega')
                    ? $request->data_previsao_entrega
                    : null;
                $createData['assistencia_fase_tecnica'] = \in_array($faseIn, $faseKeys, true) ? $faseIn : 'fila';
            }

            $ordem = OrdemServico::create($createData);
            $ordemCriadaParaCompensar = $ordem;

            // verifica ótica
            if ($request->medico_id) {
                $this->insereReceitaOtica($request, $ordem);
            }

            if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
                $this->assistenciaOsAberturaService->concluirAbertura($ordem, $request);
                $this->sincronizarStatusTradeinAssistenciaAoAbrirOs($ordem);
            }

            $ordemCriadaParaCompensar = null;
            $logRef = $ordem->isOsInterna()
                ? 'interna (loja)'
                : ('cliente — ' . ($ordem->cliente ? $ordem->cliente->info : '—'));
            __createLog($request->empresa_id, 'Ordem de Serviço', 'cadastrar', '[os_aberta] #' . $codigo_sequencial . ' — ' . $logRef);

            session()->flash("flash_success", "Ordem de Serviço criada com sucesso");

            return redirect()->route('ordem-servico.show', $ordem->id);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            if ($ordemCriadaParaCompensar) {
                $this->assistenciaOsAberturaService->compensarFalhaAbertura($ordemCriadaParaCompensar);
            }
            __createLog($request->empresa_id, 'Ordem de Serviço', 'erro', $e->getMessage());
            session()->flash("flash_error", "Algo deu Errado" . $e->getMessage());

            return redirect()->back();
        }
    }

    private function _validate(Request $request){

        $rules = [
            'data_inicio' => 'required',
            // 'data_entrega' => 'required',
        ];

        $messages = [
            'data_inicio.required' => 'Campo obrigatório',
            'data_entrega.required' => 'Campo obrigatório',
        ];

        $cfg = ConfigGeral::where('empresa_id', $request->empresa_id)->first();
        if ($cfg && $cfg->tipo_ordem_servico === 'assistencia técinica') {
            $rules['tradein_inventory_item_id'] = [
                'nullable',
                'integer',
                Rule::exists('tradein_inventory_items', 'id')->where('empresa_id', (int) $request->empresa_id),
            ];
            $rules['escopo_ordem_servico'] = 'nullable|in:' . OrdemServico::ESCOPO_CLIENTE . ',' . OrdemServico::ESCOPO_INTERNA;
            $rules['produto_aparelho_id'] = [
                'nullable',
                'integer',
                Rule::exists('produtos', 'id')->where('empresa_id', (int) $request->empresa_id),
            ];
            $rules['produto_aparelho_unico_id'] = ['nullable', 'integer', 'exists:produto_unicos,id'];
            $faseLista = implode(',', array_keys(OrdemServico::assistenciaFasesTecnicas()));
            $rules['tecnico_responsavel_id'] = [
                'nullable',
                'integer',
                Rule::exists('funcionarios', 'id')->where('empresa_id', (int) $request->empresa_id),
            ];
            $rules['data_previsao_entrega'] = ['nullable', 'date'];
            $rules['assistencia_fase_tecnica'] = ['nullable', 'in:' . $faseLista];
        }

        $this->validate($request, $rules, $messages);
    }

    private function validarAberturaOsClienteExigeCliente(Request $request, string $escopo): void
    {
        if ($escopo !== OrdemServico::ESCOPO_CLIENTE) {
            return;
        }

        $request->validate(
            [
                'cliente_id' => [
                    'required',
                    'integer',
                    Rule::exists('clientes', 'id')->where('empresa_id', (int) $request->empresa_id),
                ],
            ],
            [
                'cliente_id.required' => 'OS de cliente exige cliente vinculado.',
                'cliente_id.integer' => 'Cliente inválido para abertura da OS.',
                'cliente_id.exists' => 'Cliente inválido para esta empresa.',
            ]
        );
    }

    private function validarAberturaAssistenciaMinima(Request $request, string $escopo): void
    {
        $checklistEstados = implode(',', array_keys(OrdemServico::assistenciaChecklistFisicoEstados()));
        $rules = [
            'tipo_servico' => ['required', 'string'],
            'equipamento' => ['required', 'string', 'max:100'],
            'marca_equipamento' => ['required', 'string', 'max:120'],
            'modelo_equipamento' => ['required', 'string', 'max:120'],
            'numero_serie' => ['required', 'string', 'max:100'],
            'senha_aparelho' => ['nullable', 'string', 'max:120'],
            'acessorios' => ['required', 'string', 'max:5000'],
            'diagnostico_cliente' => ['required', 'string'],
            'checklist_fisico' => ['required', 'array'],
            'checklist_fisico_observacao' => ['nullable', 'array'],
            'fotos' => ['nullable', 'array'],
            'fotos.frente' => ['nullable', 'file', 'image', 'max:5120'],
            'fotos.verso' => ['nullable', 'file', 'image', 'max:5120'],
            'fotos.laterais' => ['nullable', 'array'],
            'fotos.laterais.*' => ['nullable', 'file', 'image', 'max:5120'],
            'fotos.outras' => ['nullable', 'array'],
            'fotos.outras.*' => ['nullable', 'file', 'image', 'max:5120'],
        ];

        foreach (array_keys(OrdemServico::assistenciaChecklistFisicoDefinicoes()) as $codigo) {
            $rules['checklist_fisico.' . $codigo] = ['required', 'string', 'in:' . $checklistEstados];
            $rules['checklist_fisico_observacao.' . $codigo] = ['nullable', 'string', 'max:1000'];
        }

        $request->validate($rules, [
            'equipamento.required' => 'Informe o aparelho/equipamento.',
            'marca_equipamento.required' => 'Informe a marca do aparelho.',
            'modelo_equipamento.required' => 'Informe o modelo do aparelho.',
            'numero_serie.required' => 'Informe o IMEI ou número de série.',
            'acessorios.required' => 'Informe os acessórios recebidos, mesmo que seja "nenhum".',
            'diagnostico_cliente.required' => 'Informe o defeito relatado pelo cliente.',
            'checklist_fisico.required' => 'Preencha o checklist físico de entrada.',
        ]);

        if ($escopo !== OrdemServico::ESCOPO_CLIENTE) {
            return;
        }

        $cliente = Cliente::where('empresa_id', (int) $request->empresa_id)
            ->where('id', (int) $request->cliente_id)
            ->first();

        if (!$cliente) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Cliente inválido para esta empresa.'],
            ]);
        }

        $faltando = [];
        if (trim((string) $cliente->razao_social) === '') {
            $faltando[] = 'nome';
        }
        if (trim((string) $cliente->cpf_cnpj) === '') {
            $faltando[] = 'CPF/CNPJ';
        }
        if (trim((string) $cliente->telefone) === '') {
            $faltando[] = 'telefone';
        }
        if (trim((string) $cliente->email) === '') {
            $faltando[] = 'e-mail';
        }

        if (!empty($faltando)) {
            throw ValidationException::withMessages([
                'cliente_id' => ['Complete o cadastro do cliente antes de abrir OS de assistência: ' . implode(', ', $faltando) . '.'],
            ]);
        }
    }

    /** Opções do select de inventário trade-in na OS (assistência). */
    private function buildTradeinOpcoesInventarioOs(): array
    {
        $vazio = ['' => '— Não vinculado —'];

        if (!Gate::allows('tradein_view')) {
            return $vazio;
        }

        $cfg = ConfigGeral::where('empresa_id', request()->empresa_id)->first();
        if (!$cfg || $cfg->tipo_ordem_servico !== 'assistencia técinica') {
            return $vazio;
        }

        $out = $vazio;
        $rows = TradeinInventoryItem::where('empresa_id', request()->empresa_id)
            ->orderByDesc('id')
            ->limit(500)
            ->get(['id', 'descricao_item', 'serial', 'valor', 'status']);

        foreach ($rows as $r) {
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

    private function insereReceitaOtica($request, $ordem){

        $file_name = '';
        if (!is_dir(public_path('files_receita'))) {
            mkdir(public_path('files_receita'), 0777, true);
        }
        if ($request->hasFile('arquivo')) {
            if($ordem->oticaOs && $ordem->oticaOs->arquivo_receita){

                if(file_exists(public_path('files_receita/') . $ordem->oticaOs->arquivo_receita)){
                    unlink(public_path('files_receita/') . $ordem->oticaOs->arquivo_receita);
                }
            }
            $file = $request->arquivo;
            $ext = $file->getClientOriginalExtension();
            $file_name = Str::random(20) . ".$ext";
            $file->move(public_path('files_receita/'), $file_name);
        }
        // dd($request->all());

        OticaOs::where('ordem_servico_id', $ordem->id)->delete();
        $data = [
            'ordem_servico_id' => $ordem->id,
            'medico_id' => $request->medico_id,
            'validade' => $request->validade,
            'arquivo_receita' => $file_name,
            'observacao_receita' => $request->observacao_receita ?? '',
            'convenio_id' => $request->convenio_id,
            'tipo_lente' => $request->tipo_lente,
            'laboratorio_id' => $request->laboratorio_id,
            'material_lente' => $request->material_lente,
            'descricao_lente' => $request->descricao_lente ?? '',
            'coloracao_lente' => $request->coloracao_lente ?? '',
            'armacao_propria' => $request->armacao_propria,
            'armacao_segue' => $request->armacao_segue,
            'formato_armacao_id' => $request->formato_armacao_id,
            'armacao_aro' => $request->armacao_aro,
            'armacao_ponte' => $request->armacao_ponte,
            'tipo_armacao_id' => $request->tipo_armacao_id,
            'armacao_maior_diagonal' => $request->armacao_maior_diagonal,
            'armacao_altura_vertical' => $request->armacao_altura_vertical,
            'armacao_distancia_pupilar' => $request->armacao_distancia_pupilar,
            'armacao_altura_centro_longe_od' => $request->armacao_altura_centro_longe_od,
            'armacao_altura_centro_longe_oe' => $request->armacao_altura_centro_longe_oe,
            'armacao_altura_centro_perto_od' => $request->armacao_altura_centro_perto_od,
            'armacao_altura_centro_perto_oe' => $request->armacao_altura_centro_perto_oe,
            'tratamentos' => $request->tratamentos ? json_encode($request->tratamentos) : '[]'
        ];

        OticaOs::create($data);

        if($request->esferico_longe_od){
            MedicaoReceitaOs::where('ordem_servico_id', $ordem->id)->delete();
            
            $data = [
                'ordem_servico_id' => $ordem->id,
                'esferico_longe_od' => $request->esferico_longe_od,
                'esferico_longe_oe' => $request->esferico_longe_oe,
                'esferico_perto_od' => $request->esferico_perto_od,
                'esferico_perto_oe' => $request->esferico_perto_oe,
                'cilindrico_longe_od' => $request->cilindrico_longe_od,
                'cilindrico_longe_oe' => $request->cilindrico_longe_oe,
                'cilindrico_perto_od' => $request->cilindrico_perto_od,
                'cilindrico_perto_oe' => $request->cilindrico_perto_oe,
                'eixo_longe_od' => $request->eixo_longe_od,
                'eixo_longe_oe' => $request->eixo_longe_oe,
                'eixo_perto_od' => $request->eixo_perto_od,
                'eixo_perto_oe' => $request->eixo_perto_oe,
                'altura_longe_od' => $request->altura_longe_od,
                'altura_longe_oe' => $request->altura_longe_oe,
                'altura_perto_od' => $request->altura_perto_od,
                'altura_perto_oe' => $request->altura_perto_oe,
                'dnp_longe_od' => $request->dnp_longe_od,
                'dnp_longe_oe' => $request->dnp_longe_oe,
                'dnp_perto_od' => $request->dnp_perto_od,
                'dnp_perto_oe' => $request->dnp_perto_oe
            ];

            MedicaoReceitaOs::create($data);
        }
    }

    public function update(Request $request, $id)
    {
        $item = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($item);

        try {
            $this->authorizePorEscopoOrdemServico($item, 'ordem_servico_edit', 'ordem_servico_interna_edit');

            $cfgEmp = ConfigGeral::where('empresa_id', $request->empresa_id)->first();
            $escopoPersistido = ($item->escopo_ordem_servico ?: OrdemServico::ESCOPO_CLIENTE) === OrdemServico::ESCOPO_INTERNA
                ? OrdemServico::ESCOPO_INTERNA
                : OrdemServico::ESCOPO_CLIENTE;

            $item->refresh();
            $snapshotAntes = OrdemServicoAuditoriaAlteracaoLogger::snapshot($item);

            if ($cfgEmp && $cfgEmp->tipo_ordem_servico === 'assistencia técinica') {
                $request->merge(['escopo_ordem_servico' => $escopoPersistido]);
            }

            if ($cfgEmp && $cfgEmp->tipo_ordem_servico === 'assistencia técinica' && $escopoPersistido === OrdemServico::ESCOPO_INTERNA) {
                $request->merge(['cliente_id' => null]);

                $this->_validate($request);

                $temLanc = TradeinInventoryItemCustoPecaOsLancamento::where('ordem_servico_id', $item->id)->exists();

                if ($temLanc) {
                    $request->merge([
                        'tradein_inventory_item_id' => $item->tradein_inventory_item_id,
                        'produto_aparelho_id' => $item->produto_aparelho_id,
                        'produto_aparelho_unico_id' => $item->produto_aparelho_unico_id,
                    ]);
                } else {
                    $this->validarEPrepararCamposOsInterna($request, $item->id);
                }
            } elseif ($cfgEmp && $cfgEmp->tipo_ordem_servico === 'assistencia técinica') {
                $request->merge([
                    'escopo_ordem_servico' => OrdemServico::ESCOPO_CLIENTE,
                    'produto_aparelho_id' => null,
                    'produto_aparelho_unico_id' => null,
                ]);

                $this->_validate($request);

                $request->validate([
                    'cliente_id' => 'required|integer|exists:clientes,id',
                    'tradein_inventory_item_id' => [
                        'nullable',
                        'integer',
                        Rule::exists('tradein_inventory_items', 'id')->where('empresa_id', (int) $request->empresa_id),
                    ],
                ]);

                Cliente::where('empresa_id', $request->empresa_id)->where('id', $request->cliente_id)->firstOrFail();

                $newTiRaw = $request->input('tradein_inventory_item_id');
                $newTi = ($newTiRaw !== null && $newTiRaw !== '') ? (int) $newTiRaw : null;

                $temLanc = TradeinInventoryItemCustoPecaOsLancamento::where('ordem_servico_id', $item->id)->exists();
                $atualTi = $item->tradein_inventory_item_id ? (int) $item->tradein_inventory_item_id : null;

                if ($temLanc && $newTi !== $atualTi) {
                    session()->flash(
                        'flash_error',
                        'Não é possível alterar ou remover o aparelho trade-in vinculado: já há registro de peças somadas ao custo nesta OS.'
                    );

                    return redirect()->back()->withInput();
                }

                $request->merge(['tradein_inventory_item_id' => $newTi]);
            } else {
                $request->validate([
                    'cliente_id' => 'required|integer|exists:clientes,id',
                ]);
                Cliente::where('empresa_id', $request->empresa_id)->where('id', $request->cliente_id)->firstOrFail();
            }

            $request->merge([
                'descricao' => $request->input('descricao'),
                'usuario_id' => get_id_user(),
                'empresa_id' => $request->empresa_id,
                'data_inicio' => $request->data_inicio,
                'data_entrega' => $request->data_entrega,
                'funcionario_id' => $request->funcionario_id,
            ]);

            if (!($cfgEmp && $cfgEmp->tipo_ordem_servico === 'assistencia técinica' && $escopoPersistido === OrdemServico::ESCOPO_INTERNA)) {
                $request->merge(['cliente_id' => $request->cliente_id]);
            } else {
                $request->merge(['cliente_id' => null]);
            }

            DB::transaction(function () use ($item, $request, $snapshotAntes): void {
                $item->fill($request->all())->save();

                $item->refresh();
                $snapshotDepois = OrdemServicoAuditoriaAlteracaoLogger::snapshot($item);
                $diff = OrdemServicoAuditoriaAlteracaoLogger::calcularDiff($snapshotAntes, $snapshotDepois);

                OrdemServicoAuditoriaAlteracaoLogger::registrarAlteracao(
                    (int) $item->empresa_id,
                    (int) $item->id,
                    $snapshotAntes,
                    $snapshotDepois,
                    $diff
                );

                if ($request->medico_id) {
                    $this->insereReceitaOtica($request, $item);
                }
            });

            $item->load('cliente');
            $logRef = $item->isOsInterna()
                ? 'interna (loja)'
                : ('cliente — ' . ($item->cliente ? $item->cliente->info : '—'));
            __createLog($request->empresa_id, 'Ordem de Serviço', 'editar', "#$item->codigo_sequencial — $logRef");
            session()->flash("flash_success", 'Ordem de Serviço alterada com sucesso');

            return redirect()->route('ordem-servico.show', $item->id);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            __createLog($request->empresa_id, 'Ordem de Serviço', 'erro', $e->getMessage());
            session()->flash("flash_error", 'Algo deu Errado' . $e->getMessage());

            return redirect()->back();
        }
    }

    public function show($id)
    {
        if (!__isCaixaAberto()) {
            session()->flash("flash_warning", "Abrir caixa antes de continuar!");
            return redirect()->route('caixa.create');
        }
        $ordem = OrdemServico::with([
            'tradeinInventoryItem.tradein',
            'tradeinInventoryItem.produto',
            'tradeinCustoPecaLancamentos.user',
            'tradeinCustoPecaLancamentos.peca',
            'produtoAparelho',
            'produtoAparelhoUnico',
            'funcionario',
            'tecnicoResponsavel',
        ])->findOrFail($id);

        __validaObjetoEmpresa($ordem);

        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_view', 'ordem_servico_interna_view');

        if ($ordem->hash_link == null) {
            $ordem->hash_link = Str::random(30);
            $ordem->save();
        }

        $ordem->load(['itens.produto', 'documentos', 'anexos', 'assistenciaChecklistFisicoItens']);
        $ordem->load(['itens.assistenciaPecaBaixa.aprovadoPor']);
        $funcionarios = Funcionario::where('empresa_id', request()->empresa_id)->get();
        $servicos = Servico::where('empresa_id', request()->empresa_id)->get();

        $veiculos = Veiculo::where('empresa_id', request()->empresa_id)
        ->where('status', 1)->get();

        $configGeral = ConfigGeral::where('empresa_id', request()->empresa_id)->first();

        $depositosPecaOsOpcoes = ['' => 'Padrão (local da OS ou ativo)'];
        $mostrarSelectDepositoOsPeca = false;

        if (AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) request()->empresa_id)) {
            $localIds = collect();
            if (function_exists('__getLocaisAtivoUsuario')) {
                $localIds = __getLocaisAtivoUsuario()->pluck('id');
            }

            $q = Deposito::where('empresa_id', request()->empresa_id)
                ->with('localizacao')
                ->orderBy('nome');

            if ($localIds->isNotEmpty()) {
                $q->whereIn('local_id', $localIds);
            }

            $depositosLista = $q->get();

            if ($depositosLista->isEmpty()) {
                $depositosLista = Deposito::where('empresa_id', request()->empresa_id)
                    ->with('localizacao')
                    ->orderBy('nome')
                    ->get();
            }

            foreach ($depositosLista as $d) {
                $depositosPecaOsOpcoes[(string) $d->id] = $d->nome . ($d->localizacao ? ' — ' . $d->localizacao->nome : '');
            }

            $mostrarSelectDepositoOsPeca = count($depositosPecaOsOpcoes) > 1;
        }

        $assistenciaControleTimeline = null;
        $assistenciaFasesTecnicasLista = null;

        if (AssistenciaOsControleTecnicoService::integraControleParaEmpresa((int) request()->empresa_id)) {
            $this->assistenciaOsControleTecnico->garantirChecklist($ordem);
            $ordem->unsetRelation('assistenciaChecklistItens')->unsetRelation('assistenciaEventos')->unsetRelation('tecnicoResponsavel');
            $ordem->load(['assistenciaChecklistItens.feitoPorUsuario', 'assistenciaEventos.user', 'tecnicoResponsavel']);
            $assistenciaControleTimeline = $this->assistenciaOsControleTecnico->montarTimeline($ordem);
            $assistenciaFasesTecnicasLista = OrdemServico::assistenciaFasesTecnicas();
        }

        return view('ordem_servico.show', compact(
            'funcionarios',
            'ordem',
            'servicos',
            'veiculos',
            'configGeral',
            'depositosPecaOsOpcoes',
            'mostrarSelectDepositoOsPeca',
            'assistenciaControleTimeline',
            'assistenciaFasesTecnicasLista',
        ));
    }

    public function assistenciaPainel(Request $request)
    {
        $cfg = ConfigGeral::where('empresa_id', request()->empresa_id)->first();
        if (!$cfg || $cfg->tipo_ordem_servico !== 'assistencia técinica') {
            abort(404);
        }

        $ativos = $this->queryOrdensServicoFiltradas($request)
            ->whereIn('ordem_servicos.estado', ['pd', 'ap']);

        $labelsFase = OrdemServico::assistenciaFasesTecnicas();

        $porFase = (clone $ativos)
            ->selectRaw("COALESCE(NULLIF(TRIM(assistencia_fase_tecnica), ''), 'fila') as fase_grp, COUNT(*) as total")
            ->groupBy('fase_grp')
            ->pluck('total', 'fase_grp');

        $estadosFin = OrdemServico::estados();
        $porEstadoFinanceiro = (clone $ativos)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->get()
            ->mapWithKeys(function ($row) use ($estadosFin) {
                $k = $row->estado;

                return [$k => ['total' => $row->total, 'label' => $estadosFin[$k] ?? $k]];
            });

        $hoje = now()->startOfDay();
        $atrasadas = (clone $ativos)
            ->whereNotNull('data_previsao_entrega')
            ->whereDate('data_previsao_entrega', '<', $hoje)
            ->with(['cliente', 'tecnicoResponsavel'])
            ->orderBy('data_previsao_entrega')
            ->limit(40)
            ->get();

        return view('ordem_servico.assistencia_painel', compact(
            'porFase',
            'labelsFase',
            'porEstadoFinanceiro',
            'atrasadas',
        ));
    }

    public function assistenciaFilaTecnica(Request $request)
    {
        $cfg = ConfigGeral::where('empresa_id', request()->empresa_id)->first();
        if (!$cfg || $cfg->tipo_ordem_servico !== 'assistencia técinica') {
            abort(404);
        }

        $data = $this->queryOrdensServicoFiltradas($request)
            ->with(['cliente', 'tecnicoResponsavel', 'funcionario'])
            ->whereIn('ordem_servicos.estado', ['pd', 'ap'])
            ->orderByRaw('CASE WHEN ordem_servicos.data_previsao_entrega IS NULL THEN 1 ELSE 0 END')
            ->orderBy('ordem_servicos.data_previsao_entrega', 'asc')
            ->orderBy('ordem_servicos.id', 'asc')
            ->paginate(__itensPagina())
            ->appends($request->query());

        $labelsFase = OrdemServico::assistenciaFasesTecnicas();
        $funcionariosFiltroAssistencia = Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get();

        return view('ordem_servico.assistencia_fila_tecnica', compact('data', 'labelsFase', 'funcionariosFiltroAssistencia'));
    }

    public function assistenciaAtualizarControle(Request $request, int $id)
    {
        $ordem = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($ordem);
        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_edit', 'ordem_servico_interna_edit');

        if (!AssistenciaOsControleTecnicoService::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            abort(404);
        }

        $faseLista = implode(',', array_keys(OrdemServico::assistenciaFasesTecnicas()));
        $request->validate([
            'assistencia_fase_tecnica' => 'nullable|in:' . $faseLista,
            'tecnico_responsavel_id' => [
                'nullable',
                'integer',
                Rule::exists('funcionarios', 'id')->where('empresa_id', (int) request()->empresa_id),
            ],
            'data_previsao_entrega' => 'nullable|date',
            'observacao_andamento' => 'nullable|string|max:5000',
        ]);

        try {
            $faseVelha = $ordem->assistencia_fase_tecnica ?: 'fila';
            $tecVelho = (int) ($ordem->tecnico_responsavel_id ?? 0);
            $previsaoVelhaTs = $ordem->data_previsao_entrega ? strtotime((string) $ordem->data_previsao_entrega) : null;

            if ($request->filled('assistencia_fase_tecnica')) {
                $ordem->assistencia_fase_tecnica = (string) $request->assistencia_fase_tecnica;
            }
            $ordem->tecnico_responsavel_id = $request->filled('tecnico_responsavel_id')
                ? (int) $request->tecnico_responsavel_id
                : null;
            $ordem->data_previsao_entrega = $request->filled('data_previsao_entrega')
                ? $request->data_previsao_entrega
                : null;
            $ordem->save();

            $labels = OrdemServico::assistenciaFasesTecnicas();
            $faseNova = $ordem->assistencia_fase_tecnica ?: 'fila';

            if ((string) $faseVelha !== (string) $faseNova) {
                $this->assistenciaOsControleTecnico->registrarEvento(
                    $ordem->fresh(),
                    'fase',
                    'Fase operacional: ' . ($labels[$faseVelha] ?? $faseVelha) . ' → ' . ($labels[$faseNova] ?? $faseNova) . '.'
                );
            }

            $tecNovo = (int) ($ordem->tecnico_responsavel_id ?? 0);
            if ($tecVelho !== $tecNovo) {
                $ordem->load('tecnicoResponsavel');
                $nome = $ordem->tecnicoResponsavel ? $ordem->tecnicoResponsavel->nome : '— (sem técnico definido)';
                $this->assistenciaOsControleTecnico->registrarEvento($ordem->fresh(), 'tecnico', 'Técnico responsável definido: ' . $nome . '.');
            }

            $previsaoNovaTs = $ordem->data_previsao_entrega ? strtotime((string) $ordem->data_previsao_entrega) : null;
            if ($previsaoVelhaTs !== $previsaoNovaTs) {
                $txt = $ordem->data_previsao_entrega
                    ? 'Previsão de entrega: ' . __data_pt($ordem->data_previsao_entrega, 0) . '.'
                    : 'Previsão de entrega removida.';
                $this->assistenciaOsControleTecnico->registrarEvento($ordem->fresh(), 'previsao_entrega', $txt);
            }

            $obs = trim((string) $request->input('observacao_andamento', ''));
            if ($obs !== '') {
                $this->assistenciaOsControleTecnico->registrarEvento($ordem->fresh(), 'observacao', $obs);
            }

            session()->flash('flash_success', 'Controle técnico atualizado.');
        } catch (\Exception $e) {
            session()->flash('flash_error', 'Não foi possível salvar: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    public function assistenciaAlternarChecklist(Request $request, int $id)
    {
        $ordem = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($ordem);
        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_edit', 'ordem_servico_interna_edit');

        if (!AssistenciaOsControleTecnicoService::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            abort(404);
        }

        $codigos = array_keys(AssistenciaOsControleTecnicoService::checklistDefinicoesPadrao());
        $request->validate([
            'item_codigo' => ['required', 'string', Rule::in($codigos)],
        ]);

        $this->assistenciaOsControleTecnico->garantirChecklist($ordem);

        $linha = OrdemServicoAssistenciaChecklistItem::where('ordem_servico_id', $ordem->id)
            ->where('item_codigo', (string) $request->item_codigo)
            ->firstOrFail();

        try {
            $novo = !$linha->feito;
            $linha->feito = $novo;
            $linha->feito_em = $novo ? now() : null;
            $linha->feito_por_user_id = $novo ? Auth::id() : null;
            $linha->save();

            $rot = $novo ? 'marcado' : 'desmarcado';
            $this->assistenciaOsControleTecnico->registrarEvento(
                $ordem->fresh(),
                'checklist',
                'Checklist "' . $linha->titulo . '" ' . $rot . '.'
            );

            session()->flash('flash_success', 'Checklist atualizado.');
        } catch (\Exception $e) {
            session()->flash('flash_error', $e->getMessage());
        }

        return redirect()->back();
    }

    public function storeServico(Request $request)
    {
        $id = $request->ordem_servico_id;
        $ordem = OrdemServico::findOrFail($id);
        $valor = $ordem->valor + (__convert_value_bd($request->valor) * __convert_value_bd($request->quantidade));
        $ordem->valor = $valor;
        $ordem->save();
        try {
            $servico = ServicoOs::create([
                'servico_id' => $request->servico_id,
                'ordem_servico_id' => $ordem->id,
                'quantidade' => __convert_value_bd($request->quantidade),
                'valor' => __convert_value_bd($request->valor),
                'status' => $request->status,
                'subtotal' => __convert_value_bd($request->quantidade) * __convert_value_bd($request->valor)
            ]);

            $descricaoLog = "#$ordem->codigo_sequencial - Serviço Adicionado: " . $servico->servico->nome;

            __createLog(request()->empresa_id, 'Ordem de Serviço - Serviço', 'cadastrar', $descricaoLog);
            session()->flash("flash_success", "Serviço adicionado!");
        } catch (\Exception $e) {
            __createLog($request->empresa_id, 'Ordem de Serviço - Serviço', 'erro', $e->getMessage());
            session()->flash("flash_error", "Algo deu errado" . $e->getMessage());
        }
        return redirect()->back();
    }

    public function storeProduto(Request $request)
    {
        $id = $request->ordem_servico_id;
        $nomeParaLog = '';
        $criouPendenciaBaixa = false;

        try {
            DB::transaction(function () use ($request, $id, &$nomeParaLog, &$criouPendenciaBaixa) {
                $ordem = OrdemServico::where('id', $id)->lockForUpdate()->firstOrFail();
                __validaObjetoEmpresa($ordem);

                $empresaId = (int) $ordem->empresa_id;
                $produtoIdRaw = $request->input('produto_id');
                $produtoId = ($produtoIdRaw !== null && $produtoIdRaw !== '' && (int) $produtoIdRaw > 0)
                    ? (int) $produtoIdRaw
                    : null;

                if ($produtoId !== null) {
                    Produto::where('empresa_id', $empresaId)->where('id', $produtoId)->firstOrFail();

                    $request->validate([
                        'quantidade_produto' => 'required',
                        'valor_produto' => 'required',
                        'deposito_os_peca_id' => 'nullable|integer',
                    ]);
                    $livreCampos = [
                        'descricao_livre' => null,
                        'marca_livre' => null,
                        'modelo_livre' => null,
                        'imei_serial_livre' => null,
                    ];
                } else {
                    $request->validate([
                        'quantidade_produto' => 'required',
                        'valor_produto' => 'required',
                        'descricao_livre' => 'required|string|min:2|max:500',
                        'marca_livre' => 'nullable|string|max:120',
                        'modelo_livre' => 'nullable|string|max:120',
                        'imei_serial_livre' => 'nullable|string|max:160',
                        'deposito_os_peca_id' => 'nullable|integer',
                    ]);
                    $produtoId = null;
                    $livreCampos = [
                        'descricao_livre' => trim((string) $request->input('descricao_livre', '')),
                        'marca_livre' => $request->filled('marca_livre') ? trim((string) $request->marca_livre) : null,
                        'modelo_livre' => $request->filled('modelo_livre') ? trim((string) $request->modelo_livre) : null,
                        'imei_serial_livre' => $request->filled('imei_serial_livre') ? trim((string) $request->imei_serial_livre) : null,
                    ];
                }

                $quantidade = __convert_value_bd($request->quantidade_produto);
                $valorUnit = __convert_value_bd($request->valor_produto);
                $subtotal = $quantidade * $valorUnit;

                $produtoOs = ProdutoOs::create(array_merge([
                    'produto_id' => $produtoId,
                    'ordem_servico_id' => $ordem->id,
                    'quantidade' => $quantidade,
                    'valor' => $valorUnit,
                    'subtotal' => $subtotal,
                ], $livreCampos));

                $produtoOs->load('produto');
                $nomeParaLog = $produtoOs->descricaoLinha();

                $ordem->valor = $ordem->valor + $subtotal;
                $ordem->save();

                if ($produtoId !== null
                    && AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) $ordem->empresa_id)) {
                    $depositoId = $request->filled('deposito_os_peca_id') ? (int) $request->deposito_os_peca_id : null;
                    if ($ordem->isOsInterna() && !empty($ordem->tradein_inventory_item_id)) {
                        $this->assistenciaOsPecaBaixaPendenteService->criarPendente($ordem, $produtoOs, $depositoId ?: null);
                        $criouPendenciaBaixa = true;
                    } else {
                        $this->assistenciaOsEstoque->aplicarBaixa($ordem, $produtoOs, $depositoId ?: null);
                    }
                }
            });

            $ordemFresh = OrdemServico::findOrFail($id);

            $descricaoLog = '#' . $ordemFresh->codigo_sequencial . ' — Produto: ' . $nomeParaLog;

            __createLog(request()->empresa_id, 'Ordem de Serviço - Produto', 'cadastrar', $descricaoLog);
            session()->flash('flash_success', $criouPendenciaBaixa
                ? 'Produto adicionado com pendência de baixa.'
                : 'Produto adicionado!');
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            __createLog($request->empresa_id, 'Ordem de Serviço - Produto', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Algo deu errado: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    public function deletarProduto($id)
    {
        try {
            DB::transaction(function () use ($id) {
                $produtoOs = ProdutoOs::where('id', $id)->lockForUpdate()->firstOrFail();
                $ordem = OrdemServico::where('id', $produtoOs->ordem_servico_id)->lockForUpdate()->firstOrFail();

                $nomeProduto = $produtoOs->descricaoLinha();
                $deveEstornarEstoque = true;

                if ($ordem->isOsInterna() && !empty($ordem->tradein_inventory_item_id)) {
                    $pendencia = $this->assistenciaOsPecaBaixaPendenteService->pendenciaDaLinha((int) $produtoOs->id);

                    if (!$pendencia) {
                        throw new \DomainException('Esta peça não possui pendência de baixa registrada e não pode ser removida neste fluxo.');
                    }

                    if ($pendencia && $pendencia->status === AssistenciaOsPecaBaixa::STATUS_BAIXADO) {
                        throw new \DomainException('Esta peça já teve baixa confirmada e não pode ser removida neste fluxo.');
                    }

                    if ($pendencia->status === AssistenciaOsPecaBaixa::STATUS_PENDENTE) {
                        $this->assistenciaOsPecaBaixaPendenteService->cancelarPendente($produtoOs);
                        $deveEstornarEstoque = false;
                    }
                }

                if ($deveEstornarEstoque
                    && AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) $ordem->empresa_id)) {
                    $this->assistenciaOsEstoque->aplicarEstorno($ordem, $produtoOs);
                }

                $ordem->valor = $ordem->valor - $produtoOs->subtotal;
                $ordem->save();
                $produtoOs->delete();

                $descricaoLog = '#' . $ordem->codigo_sequencial . ' — Produto removido: ' . $nomeProduto;
                __createLog(request()->empresa_id, 'Ordem de Serviço - Produto', 'excluir', $descricaoLog);
            });

            session()->flash('flash_success', 'Produto removido');
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Ordem de Serviço - Produto', 'erro', $e->getMessage());
            session()->flash('flash_error', 'Algo deu errado: ' . $e->getMessage());
        }

        return redirect()->back();
    }

    public function deletarServico($id)
    {
        $produtoOs = ServicoOs::where('id', $id)->first();
        $ordem = OrdemServico::where('id', $produtoOs->ordem_servico_id)->first();
        $valor = $ordem->valor - $produtoOs->subtotal;
        $ordem->valor = $valor;
        $ordem->save();
        try {
            $produtoOs->delete();
            $descricaoLog = "#$ordem->codigo_sequencial - Serviço Removido: " . $produtoOs->servico->nome;
            __createLog(request()->empresa_id, 'Ordem de Serviço - Serviço', 'excluir', $descricaoLog);

            session()->flash("flash_success", "Serviço removido");
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Ordem de Serviço - Serviço', 'erro', $e->getMessage());
            session()->flash("flash_error", "Algo deu errado" . $e->getMessage());
        }
        return redirect()->back();
    }

    public function alterarStatusServico($id)
    {
        $servicoOs = ServicoOs::where('id', $id)->first();
        try {
            $servicoOs->status = !$servicoOs->status;
            $servicoOs->save();
            session()->flash("flash_success", "Status Alterado");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu errado" . $e->getMessage());
        }
        return redirect()->back();
    }

    public function storeFuncionario(Request $request)
    {
        $id = $request->ordem_servico_id;
        $ordem = OrdemServico::findOrFail($id);
        // $this->_validateFuncionario($request);
        try {
            FuncionarioOs::create([
                'usuario_id' => get_id_user(),
                'funcionario_id' => $request->funcionario_id,
                'ordem_servico_id' => $request->ordem_servico_id,
                'funcao' => $request->funcao
            ]);
            session()->flash("flash_success", "Funcionario Adicionado a Ordem de Serviço");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu Errado: " . $e->getMessage());
        }
        return redirect()->back();
    }

    public function addRelatorio($id)
    {
        $ordem = OrdemServico::where('id', $id)->first();
        return view('ordem_servico.add_relatorio', compact('ordem'));
    }

    public function storeRelatorio(Request $request)
    {
        // dd($request->ordem_servico_id);
        try {
            RelatorioOs::create([
                'usuario_id' => get_id_user(),
                'texto' => $request->texto,
                'ordem_servico_id' => $request->ordem_servico_id
            ]);
            session()->flash("flash_success", "Relatório Adicionado");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu errado: " . $e->getMessage());
        }
        return redirect()->route('ordem-servico.show', $request->ordem_servico_id);
    }

    public function alterarEstado($id)
    {
        $ordem = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($ordem);
        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_edit', 'ordem_servico_interna_edit');
        $configGeral = ConfigGeral::where('empresa_id', $ordem->empresa_id)->first();

        return view('ordem_servico.alterar_estado', compact('ordem', 'configGeral'));
    }

    public function updateEstado(Request $request, $id)
    {
        $ordem = OrdemServico::findOrFail($id);
        __validaObjetoEmpresa($ordem);
        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_edit', 'ordem_servico_interna_edit');

        $estadoAnterior = $ordem->estado;
        $novoEstado = $this->validarPayloadAlteracaoEstado($request, $ordem);
        $finalizaFinanceiro = $this->deveFinalizarFinanceiramente($request, $novoEstado);
        $deveGerarTermoFinal = $this->assistenciaOsFinalizacaoService->deveGerarTermoFinal($ordem, $novoEstado);
        $caixa = null;

        if($finalizaFinanceiro){
            $caixa = __isCaixaAberto();
            if($caixa == null){
                session()->flash("flash_error", "Abra o caixa!");
                return redirect()->back()->withInput();
            }
        }

        try {
            if($finalizaFinanceiro && $this->finalizacaoFinanceiraJaProcessada($ordem)) {
                    session()->flash("flash_error", "Esta OS já possui financeiro vinculado e não pode gerar novo faturamento.");
                    return redirect()->route('ordem-servico.show', [$ordem->id]);
            }

            $ordem = $this->assistenciaOsFinalizacaoService->finalizar(
                $ordem,
                $novoEstado,
                $request->faturar,
                $deveGerarTermoFinal,
                $finalizaFinanceiro ? function (OrdemServico $ordemBloqueada) use ($request, $caixa): void {
                    $ordemBloqueada->caixa_id = $caixa->id;
                    $ordemBloqueada->save();

                    if($request->tipo_pagamento && $request->tipo_pagamento[0]){
                        $this->persistirFaturasOrdemServico($request, $ordemBloqueada);
                        $this->persistirContasReceberOrdemServico($request, $ordemBloqueada, $caixa);
                    }
                } : null
            );

            if ($estadoAnterior !== $novoEstado) {
                __createLog(
                    request()->empresa_id,
                    'Ordem de Serviço',
                    'editar',
                    '[os_estado_alterado] OS #' . $ordem->codigo_sequencial . ': ' . $estadoAnterior . ' → ' . $novoEstado
                );
            }
            if ($novoEstado === 'fz') {
                __createLog(
                    request()->empresa_id,
                    'Ordem de Serviço',
                    'editar',
                    '[os_finalizada] OS #' . $ordem->codigo_sequencial
                );
            }

            if (AssistenciaOsControleTecnicoService::integraControleParaEmpresa((int) $ordem->empresa_id) && $estadoAnterior !== $novoEstado) {
                $estadosLbl = OrdemServico::estados();
                $ant = $estadosLbl[$estadoAnterior] ?? $estadoAnterior;
                $nov = $estadosLbl[$novoEstado] ?? $novoEstado;
                $this->assistenciaOsControleTecnico->registrarEvento($ordem->fresh(), 'estado', 'Estado da OS: ' . $ant . ' → ' . $nov . '.');
            }

            session()->flash("flash_success", "Estado alterado!");
        } catch (QueryException $e) {
            if ($this->violacaoUnicaFinalizacaoFinanceira($e)) {
                session()->flash("flash_error", "Esta OS já possui financeiro vinculado e não pode gerar novo faturamento.");
                return redirect()->route('ordem-servico.show', [$ordem->id]);
            }

            session()->flash("flash_error", "Algo deu errado: " . $e->getMessage());
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu errado: " . $e->getMessage());
        }
        return redirect()->route('ordem-servico.show', [$ordem->id]);
    }

    private function validarPayloadAlteracaoEstado(Request $request, OrdemServico $ordem): string
    {
        if (!$request->filled('novo_estado') && $request->filled('estado')) {
            $request->merge(['novo_estado' => $request->input('estado')]);
        }

        foreach (['tipo_pagamento', 'data_vencimento', 'valor_fatura'] as $campoArrayLegado) {
            $valor = $request->input($campoArrayLegado);
            if ($valor !== null && !is_array($valor)) {
                $request->merge([$campoArrayLegado => [$valor]]);
            }
        }

        $request->validate(
            [
                'novo_estado' => 'required|string',
                'faturar' => 'nullable|boolean',
                'tipo_pagamento' => 'nullable|array',
                'tipo_pagamento.*' => 'nullable',
                'data_vencimento' => 'nullable|array',
                'data_vencimento.*' => 'nullable|date',
                'valor_fatura' => 'nullable|array',
                'valor_fatura.*' => 'nullable|string',
            ],
            [
                'novo_estado.required' => 'Informe o estado de destino da OS.',
            ]
        );

        $novoEstado = trim((string) $request->input('novo_estado'));
        if (!OrdemServico::estadoEhValido($novoEstado)) {
            throw ValidationException::withMessages([
                'novo_estado' => ['Estado inválido para a OS.'],
            ]);
        }

        if ($ordem->estado === 'fz' && $novoEstado === 'fz') {
            throw ValidationException::withMessages([
                'novo_estado' => ['Esta OS já está finalizada e não pode ser finalizada novamente.'],
            ]);
        }

        if (!$ordem->podeTransicionarEstadoPara($novoEstado)) {
            $estados = OrdemServico::estados();
            $permitidos = array_map(function (string $estado) use ($estados): string {
                return $estados[$estado] ?? $estado;
            }, $ordem->estadosDestinoPermitidos());

            $msgPermitidos = empty($permitidos)
                ? 'nenhum estado'
                : implode(', ', $permitidos);

            throw ValidationException::withMessages([
                'novo_estado' => [
                    'Transição de estado inválida para esta OS. A partir de "' .
                    ($estados[$ordem->estado] ?? $ordem->estado) .
                    '" só é permitido: ' . $msgPermitidos . '.',
                ],
            ]);
        }

        if ($this->deveFinalizarFinanceiramente($request, $novoEstado)) {
            $this->validarParcelasFinalizacaoFinanceira($request);

            if ($this->finalizacaoFinanceiraJaProcessada($ordem)) {
                throw ValidationException::withMessages([
                    'faturar' => ['Esta OS já possui financeiro vinculado e não pode gerar novo faturamento.'],
                ]);
            }
        }

        return $novoEstado;
    }

    private function deveFinalizarFinanceiramente(Request $request, string $novoEstado): bool
    {
        return $novoEstado === 'fz' && $request->boolean('faturar');
    }

    private function chaveDedupeFinalizacaoFinanceira(OrdemServico $ordem): string
    {
        return 'ordem_servico_finalizacao_financeira:v1:' . (int)$ordem->empresa_id . ':' . (int)$ordem->id;
    }

    private function finalizacaoFinanceiraJaProcessada(OrdemServico $ordem): bool
    {
        $chaveDedupe = $this->chaveDedupeFinalizacaoFinanceira($ordem);

        return $this->ordemServicoPossuiFinanceiro($ordem, $chaveDedupe);
    }

    private function persistirFaturasOrdemServico(Request $request, OrdemServico $ordem): void
    {
        $chaveDedupe = $this->chaveDedupeFinalizacaoFinanceira($ordem);

        for($i=0; $i<sizeof($request->tipo_pagamento); $i++){
            $fatura = new FaturaOrdemServico([
                'ordem_servico_id' => $ordem->id,
                'tipo_pagamento' => $request->tipo_pagamento[$i],
                'data_vencimento' => $request->data_vencimento[$i],
                'valor' => __convert_value_bd($request->valor_fatura[$i]),
            ]);
            $fatura->forceFill([
                'finalizacao_financeira_chave' => $chaveDedupe,
                'finalizacao_financeira_parcela' => $i + 1,
            ]);
            $fatura->save();
        }
    }

    private function persistirContasReceberOrdemServico(Request $request, OrdemServico $ordem, $caixa): void
    {
        $chaveDedupe = $this->chaveDedupeFinalizacaoFinanceira($ordem);

        for($i=0; $i<sizeof($request->tipo_pagamento); $i++){
            if (strtotime($request->data_vencimento[$i]) >= strtotime(date('Y-m-d')) && $ordem->cliente_id) {
                $conta = new ContaReceber([
                    'empresa_id' => $ordem->empresa_id,
                    'ordem_servico_id' => $ordem->id,
                    'cliente_id' => $ordem->cliente_id,
                    'valor_integral' => __convert_value_bd($request->valor_fatura[$i]),
                    'tipo_pagamento' => $request->tipo_pagamento[$i],
                    'data_vencimento' => $request->data_vencimento[$i],
                    'local_id' => $caixa->local_id,
                ]);
                $conta->forceFill([
                    'finalizacao_financeira_chave' => $chaveDedupe,
                    'finalizacao_financeira_parcela' => $i + 1,
                ]);
                $conta->save();
            }
        }
    }

    private function validarParcelasFinalizacaoFinanceira(Request $request): void
    {
        $tiposPagamento = $request->input('tipo_pagamento', []);
        $datasVencimento = $request->input('data_vencimento', []);
        $valoresFatura = $request->input('valor_fatura', []);

        $tiposPagamento = is_array($tiposPagamento) ? $tiposPagamento : [];
        $datasVencimento = is_array($datasVencimento) ? $datasVencimento : [];
        $valoresFatura = is_array($valoresFatura) ? $valoresFatura : [];

        $qtdTiposPagamento = count($tiposPagamento);
        $qtdDatasVencimento = count($datasVencimento);
        $qtdValoresFatura = count($valoresFatura);

        if ($qtdTiposPagamento === 0) {
            throw ValidationException::withMessages([
                'tipo_pagamento' => ['Informe ao menos uma parcela para gerar o faturamento.'],
            ]);
        }

        if ($qtdTiposPagamento !== $qtdDatasVencimento || $qtdTiposPagamento !== $qtdValoresFatura) {
            throw ValidationException::withMessages([
                'tipo_pagamento' => ['As parcelas de pagamento estão inconsistentes. Confira tipo, vencimento e valor de cada parcela.'],
            ]);
        }

        $chavesTiposPagamento = array_keys($tiposPagamento);
        if ($chavesTiposPagamento !== array_keys($datasVencimento) || $chavesTiposPagamento !== array_keys($valoresFatura)) {
            throw ValidationException::withMessages([
                'tipo_pagamento' => ['As parcelas de pagamento estão inconsistentes. Confira tipo, vencimento e valor de cada parcela.'],
            ]);
        }

        foreach ($chavesTiposPagamento as $chave) {
            if (trim((string)($tiposPagamento[$chave] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'tipo_pagamento.' . $chave => ['Informe o tipo de pagamento da parcela.'],
                ]);
            }

            if (trim((string)($datasVencimento[$chave] ?? '')) === '') {
                throw ValidationException::withMessages([
                    'data_vencimento.' . $chave => ['Informe a data de vencimento da parcela.'],
                ]);
            }

            $valor = $valoresFatura[$chave] ?? null;
            if ($valor === null || trim((string)$valor) === '' || __convert_value_bd((string)$valor) <= 0) {
                throw ValidationException::withMessages([
                    'valor_fatura.' . $chave => ['Informe um valor maior que zero para a parcela.'],
                ]);
            }
        }

        $request->merge([
            'tipo_pagamento' => array_values($tiposPagamento),
            'data_vencimento' => array_values($datasVencimento),
            'valor_fatura' => array_values($valoresFatura),
        ]);
    }

    private function ordemServicoPossuiFinanceiro(OrdemServico $ordem, string $chaveDedupe): bool
    {
        if ($chaveDedupe !== $this->chaveDedupeFinalizacaoFinanceira($ordem)) {
            return true;
        }

        return FaturaOrdemServico::where('ordem_servico_id', $ordem->id)->exists()
            || ContaReceber::where('ordem_servico_id', $ordem->id)->exists();
    }

    private function violacaoUnicaFinalizacaoFinanceira(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? null;
        $driverCode = (int)($e->errorInfo[1] ?? 0);
        $message = $e->getMessage();

        return $sqlState === '23000'
            && $driverCode === 1062
            && (
                str_contains($message, 'fos_fin_chave_parcela_unique')
                || str_contains($message, 'cr_fin_chave_parcela_unique')
            );
    }

    public function imprimir($id)
    {
        $ordem = OrdemServico::with([
            'produtoAparelho',
            'produtoAparelhoUnico',
            'itens.produto',
            'servicos.servico',
            'assistenciaChecklistFisicoItens',
            'anexos',
        ])->findOrFail($id);

        __validaObjetoEmpresa($ordem);

        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_view', 'ordem_servico_interna_view');
        $config = Empresa::where('id', request()->empresa_id)->first();
        if ($config == null) {
            session()->flash("flash_warning", "Configure o emitente");
            return redirect()->route('config.index');
        }

        $configGeral = ConfigGeral::where('empresa_id', $ordem->empresa_id)->first();

        $p = view('ordem_servico.imprimir', compact('config', 'ordem', 'configGeral'));

        $domPdf = new Dompdf(["enable_remote" => true]);
        $domPdf->loadHtml($p);
        $pdf = ob_get_clean();
        $domPdf->setPaper("A4");
        $domPdf->render();
        $domPdf->stream("Ordem de Serviço.pdf", array("Attachment" => false));
        // return view('ordem_servico.print', compact('ordem', 'config'));
    }

    public function editRelatorio($id)
    {
        $item = RelatorioOs::findOrFail($id);

        $ordem = OrdemServico::where('id', $item->ordem_servico_id)->first();

        return view('ordem_servico.edit_relatorio', compact('item', 'ordem'));
    }

    public function updateRelatorio(Request $request, $id)
    {
        $ordem = RelatorioOs::findOrFail($id);
        $item = OrdemServico::findOrFail($request->ordem_servico_id);
        try {
            $ordem->texto = $request->texto;
            $ordem->save();
            session()->flash("flash_success", "Reletório Alterado");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu errado" . $e->getMessage());
        }
        return redirect()->route('ordem-servico.show', $item);
    }

    public function deleteRelatorio(Request $request, $id)
    {
        $relatorioOs = RelatorioOs::where('id', $id)->first();
        try {
            $relatorioOs->delete();
            session()->flash("flash_success", "Relatório Deletado");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu errado: " . $e->getMessage());
        }
        return redirect()->back();
    }

    public function destroy(Request $request, $id)
    {
        $request->validate([
            'motivo_auditoria_os' => 'required|string|min:10|max:2000',
        ]);

        $item = OrdemServico::with(['itens.produto', 'servicos', 'cliente'])->findOrFail($id);
        __validaObjetoEmpresa($item);

        try {
            DB::transaction(function () use ($item, $request) {
                $motivo = (string) $request->motivo_auditoria_os;

                OrdemServicoAuditoriaAlteracaoLogger::registrarExclusaoPlanejada(
                    (int) $item->empresa_id,
                    (int) $item->id,
                    $motivo,
                    OrdemServicoAuditoriaAlteracaoLogger::snapshotExclusao($item)
                );

                if (AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) $item->empresa_id)) {
                    foreach ($item->itens as $linha) {
                        $this->assistenciaOsEstoque->aplicarEstorno($item, $linha);
                    }
                }

                $descricaoLog = '#' . $item->codigo_sequencial . ' — ' . ($item->isOsInterna()
                    ? 'OS interna (loja)'
                    : ('Cliente ' . ($item->cliente ? $item->cliente->info : '—')));

                $item->servicos()->delete();
                $item->relatorios()->delete();
                $item->fatura()->delete();
                $item->itens()->delete();

                $item->delete();

                __createLog(request()->empresa_id, 'Ordem de Serviço', 'excluir', $descricaoLog, [
                    'payload' => [
                        'tipo' => 'exclusao_ordem_servico',
                        'ordem_servico_id' => $item->id,
                        'motivo_auditoria' => $motivo,
                    ],
                ]);
            });

            session()->flash("flash_success", "Ordem de Serviço removida");
        } catch (\Exception $e) {
            __createLog(request()->empresa_id, 'Ordem de Serviço', 'erro', $e->getMessage());
            session()->flash("flash_error", "Algo deu errado: " . $e->getMessage());
        }

        return redirect()->back();
    }

    public function gerarNfe($id)
    {
        $item = OrdemServico::findOrFail($id);
        $cidades = Cidade::all();
        $transportadoras = Transportadora::where('empresa_id', request()->empresa_id)->get();

        $naturezas = NaturezaOperacao::where('empresa_id', request()->empresa_id)->get();
        if (sizeof($naturezas) == 0) {
            session()->flash("flash_warning", "Primeiro cadastre um natureza de operação!");
            return redirect()->route('natureza-operacao.create');
        } 
        // $produtos = Produto::where('empresa_id', request()->empresa_id)->get();
        $empresa = Empresa::findOrFail(request()->empresa_id);
        $numeroNfe = Nfe::lastNumero($empresa);

        $isOrdemServico = 1;
        $funcionarios = Funcionario::where('empresa_id', request()->empresa_id)->get();

        $naturezaPadrao = NaturezaOperacao::where('empresa_id', request()->empresa_id)
        ->where('padrao', 1)->first();

        return view('nfe.create', compact('item', 'cidades', 'transportadoras', 'naturezas', 'isOrdemServico', 'numeroNfe', 'funcionarios',
            'naturezaPadrao'));
    }

    public function destroySelecet(Request $request)
    {
        $request->validate([
            'motivo_auditoria_os' => 'required|string|min:10|max:2000',
            'item_delete' => 'required|array|min:1',
            'item_delete.*' => 'integer|exists:ordem_servicos,id',
        ]);

        $motivo = (string) $request->motivo_auditoria_os;
        $removidos = 0;

        foreach ($request->item_delete as $osId) {
            $item = OrdemServico::with(['itens.produto', 'servicos', 'cliente'])->findOrFail($osId);
            __validaObjetoEmpresa($item);

            try {
                DB::transaction(function () use ($item, $motivo) {
                    OrdemServicoAuditoriaAlteracaoLogger::registrarExclusaoPlanejada(
                        (int) $item->empresa_id,
                        (int) $item->id,
                        $motivo,
                        OrdemServicoAuditoriaAlteracaoLogger::snapshotExclusao($item)
                    );

                    if (AssistenciaOsEstoqueService::integraEstoqueParaEmpresa((int) $item->empresa_id)) {
                        foreach ($item->itens as $linha) {
                            $this->assistenciaOsEstoque->aplicarEstorno($item, $linha);
                        }
                    }

                    $descricaoLog = '#' . $item->codigo_sequencial . ' — ' . ($item->isOsInterna()
                        ? 'OS interna (loja)'
                        : ('Cliente ' . ($item->cliente ? $item->cliente->info : '—')));

                    $item->servicos()->delete();
                    $item->relatorios()->delete();
                    $item->fatura()->delete();
                    $item->itens()->delete();
                    $item->delete();

                    __createLog(request()->empresa_id, 'Ordem de Serviço', 'excluir', $descricaoLog, [
                        'payload' => [
                            'tipo' => 'exclusao_ordem_servico_lote',
                            'ordem_servico_id' => $item->id,
                            'motivo_auditoria' => $motivo,
                        ],
                    ]);
                });
                $removidos++;
            } catch (\Exception $e) {
                __createLog(request()->empresa_id, 'Ordem de Serviço', 'erro', $e->getMessage());
                session()->flash('flash_error', 'Algo deu errado: ' . $e->getMessage());

                return redirect()->back();
            }
        }

        session()->flash("flash_success", "Total de itens removidos: $removidos!");

        return redirect()->back();
    }

    public function printOtica(Request $request){
        $ordem = OrdemServico::findOrFail($request->ordem_servico_id);
        __validaObjetoEmpresa($ordem);

        $config = Empresa::where('id', request()->empresa_id)->first();
        $config = __objetoParaEmissao($config, $ordem->local_id);

        $viaCliente = $request->via_cliente;
        $viaLaboratorio = $request->via_laboratorio;
        $os = $request->os;

        $p = view('ordem_servico.print_otica', compact('config', 'ordem', 'viaCliente', 'viaLaboratorio', 'os'));

        $domPdf = new Dompdf(["enable_remote" => true]);
        $domPdf->loadHtml($p);
        $pdf = ob_get_clean();
        $domPdf->setPaper("A4");
        $domPdf->render();
        $domPdf->stream("Impressão Ótica.pdf", array("Attachment" => false));
    }

    public function updateEntrega(Request $request, $id){
        $item = OrdemServico::findOrFail($id);
        try{

            $item->adiantamento = __convert_value_bd($request->adiantamento);
            $item->data_entrega = $request->data_entrega;
            $item->veiculo_id = $request->veiculo_id ?? null;
            $item->save();

            session()->flash("flash_success", "Ordem de Serviço alterada com sucesso");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu Errado: " . $e->getMessage());
        }
        return redirect()->back();
    }

    public function updateDescricao(Request $request, $id){
        $item = OrdemServico::findOrFail($id);
        try{

            $item->descricao = $request->descricao ?? '';
            $item->save();

            session()->flash("flash_success", "Ordem de Serviço alterada com sucesso");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu Errado: " . $e->getMessage());
        }
        return redirect()->back();
    }

    public function updateDiagnostico(Request $request, $id){
        $item = OrdemServico::findOrFail($id);
        try{

            $item->diagnostico_tecnico = $request->diagnostico_tecnico ?? '';
            $item->save();

            session()->flash("flash_success", "Ordem de Serviço alterada com sucesso");
        } catch (\Exception $e) {
            session()->flash("flash_error", "Algo deu Errado: " . $e->getMessage());
        }
        return redirect()->back();
    }

    public function downloadArquivo($id){
        $item = OrdemServico::findOrFail($id);

        if ($item->oticaOs->arquivo_receita && file_exists(public_path('files_receita/') . $item->oticaOs->arquivo_receita)) {
            return response()->download(public_path('files_receita/') . $item->oticaOs->arquivo_receita);
        } else {
            session()->flash("flash_error", "Arquivo não encontrado");
            return redirect()->back();
        }
    }

    public function downloadDocumento($id)
    {
        $documento = OrdemServicoDocumento::with('ordemServico')->findOrFail($id);
        $ordem = $documento->ordemServico;

        if (!$ordem) {
            abort(404);
        }

        __validaObjetoEmpresa($ordem);
        $this->authorizePorEscopoOrdemServico($ordem, 'ordem_servico_view', 'ordem_servico_interna_view');

        $path = $this->ordemServicoDocumentoService->resolverCaminhoFisico($documento);
        if (!$path || !file_exists($path)) {
            session()->flash("flash_error", "Documento não encontrado");
            return redirect()->back();
        }

        $nomeArquivo = $documento->arquivo ?: ('os_' . (int) $ordem->codigo_sequencial . '_' . $documento->tipo . '.pdf');

        return response()->file($path, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nomeArquivo . '"',
        ]);
    }

    public function metas(Request $request){
        $metas = MetaResultado::where('empresa_id', $request->empresa_id)
        ->where('tabela', 'Ordens de Serviço')
        ->get();

        if(sizeof($metas) == 0){
            session()->flash("flash_warning", "Defina uma meta para ordem de serviço!");
            return redirect()->route('metas.index');
        }

        $totalMeta = $metas->sum('valor');
        $somaOsMes = $this->somaOsMes($request->empresa_id);

        return view('ordem_servico.metas', compact('metas', 'totalMeta', 'somaOsMes'));
    }

    private function somaOsMes($empresa_id){
        $soma = OrdemServico::where('empresa_id', $empresa_id)
        ->where('estado', '!=', 'cancelado')
        ->whereMonth('created_at', date('m'))
        ->sum('valor');
        
        return $soma;
    }

    private function authorizePorEscopoOrdemServico(OrdemServico $ordem, string $permissaoCliente, string $permissaoInterna): void
    {
        if ($ordem->isOsInterna()) {
            Gate::authorize($permissaoInterna);
        } else {
            Gate::authorize($permissaoCliente);
        }
    }

    /** OS interna em andamento: pendente ou aprovada (não finalizada nem reprovada). */
    private function queryOsInternaAbertaVinculoAparelho(int $empresaId, ?int $ignorarOrdemServicoId = null)
    {
        $q = OrdemServico::where('empresa_id', $empresaId)
            ->where('escopo_ordem_servico', OrdemServico::ESCOPO_INTERNA)
            ->whereIn('estado', ['pd', 'ap']);

        if ($ignorarOrdemServicoId) {
            $q->where('id', '!=', $ignorarOrdemServicoId);
        }

        return $q;
    }

    private function osInternaAbertaParaTradein(int $empresaId, int $tradeinId, ?int $ignorarOrdemServicoId = null): bool
    {
        return $this->queryOsInternaAbertaVinculoAparelho($empresaId, $ignorarOrdemServicoId)
            ->where('tradein_inventory_item_id', $tradeinId)
            ->exists();
    }

    private function osInternaAbertaParaProdutoUnico(int $empresaId, int $produtoUnicoId, ?int $ignorarOrdemServicoId = null): bool
    {
        return $this->queryOsInternaAbertaVinculoAparelho($empresaId, $ignorarOrdemServicoId)
            ->where('produto_aparelho_unico_id', $produtoUnicoId)
            ->exists();
    }

    private function osInternaAbertaParaProdutoLote(int $empresaId, int $produtoId, ?int $ignorarOrdemServicoId = null): bool
    {
        return $this->queryOsInternaAbertaVinculoAparelho($empresaId, $ignorarOrdemServicoId)
            ->where('produto_aparelho_id', $produtoId)
            ->whereNull('produto_aparelho_unico_id')
            ->exists();
    }

    /**
     * OS interna: exatamente uma fonte — trade-in OU produto de estoque (com serial quando tipo_unico).
     */
    private function validarEPrepararCamposOsInterna(Request $request, ?int $ignorarOrdemServicoId = null): void
    {
        $empresaId = (int) $request->empresa_id;

        $tradeinRaw = $request->input('tradein_inventory_item_id');
        $tradeinId = ($tradeinRaw !== null && $tradeinRaw !== '') ? (int) $tradeinRaw : null;

        $produtoRaw = $request->input('produto_aparelho_id');
        $produtoApId = ($produtoRaw !== null && $produtoRaw !== '') ? (int) $produtoRaw : null;

        $unicoRaw = $request->input('produto_aparelho_unico_id');
        $unicoId = ($unicoRaw !== null && $unicoRaw !== '') ? (int) $unicoRaw : null;

        $temTradein = $tradeinId !== null;
        $temProduto = $produtoApId !== null;

        if ($temTradein === $temProduto) {
            throw ValidationException::withMessages([
                'produto_aparelho_id' => ['Informe um aparelho trade-in ou um produto de estoque da loja (apenas uma das opções).'],
            ]);
        }

        if ($temTradein) {
            $ti = TradeinInventoryItem::where('empresa_id', $empresaId)->find($tradeinId);
            if (!$ti) {
                throw ValidationException::withMessages([
                    'tradein_inventory_item_id' => ['Item de trade-in inválido para esta empresa.'],
                ]);
            }
            if (!in_array((string) $ti->status, [
                TradeinInventoryItem::STATUS_PENDING_TRANSFER,
                TradeinInventoryItem::STATUS_EM_ASSISTENCIA,
            ], true)) {
                throw ValidationException::withMessages([
                    'tradein_inventory_item_id' => ['O item de trade-in não está disponível para abertura de OS interna.'],
                ]);
            }
            if ($this->osInternaAbertaParaTradein($empresaId, $tradeinId, $ignorarOrdemServicoId)) {
                throw ValidationException::withMessages([
                    'tradein_inventory_item_id' => ['Já existe ordem de serviço interna em aberto para este trade-in.'],
                ]);
            }
            $request->merge([
                'tradein_inventory_item_id' => $tradeinId,
                'produto_aparelho_id' => null,
                'produto_aparelho_unico_id' => null,
            ]);

            return;
        }

        $produto = Produto::where('empresa_id', $empresaId)->find($produtoApId);
        if (!$produto) {
            throw ValidationException::withMessages([
                'produto_aparelho_id' => ['Produto inválido para esta empresa.'],
            ]);
        }

        if ((bool) $produto->tipo_unico) {
            if (!$unicoId) {
                throw ValidationException::withMessages([
                    'produto_aparelho_unico_id' => ['Selecione o serial (unidade) do produto.'],
                ]);
            }
            $unico = ProdutoUnico::where('id', $unicoId)
                ->where('produto_id', $produto->id)
                ->where('tipo', 'entrada')
                ->where('em_estoque', 1)
                ->first();
            if (!$unico) {
                throw ValidationException::withMessages([
                    'produto_aparelho_unico_id' => ['Serial inválido, não pertence ao produto ou não está disponível em estoque.'],
                ]);
            }
            if ($this->osInternaAbertaParaProdutoUnico($empresaId, $unicoId, $ignorarOrdemServicoId)) {
                throw ValidationException::withMessages([
                    'produto_aparelho_unico_id' => ['Já existe ordem de serviço interna em aberto para este serial.'],
                ]);
            }
            $request->merge([
                'tradein_inventory_item_id' => null,
                'produto_aparelho_id' => (int) $produto->id,
                'produto_aparelho_unico_id' => $unicoId,
            ]);

            return;
        }

        if ($unicoId) {
            throw ValidationException::withMessages([
                'produto_aparelho_unico_id' => ['Este produto não usa controle por serial; não informe unidade.'],
            ]);
        }
        if ($this->osInternaAbertaParaProdutoLote($empresaId, (int) $produto->id, $ignorarOrdemServicoId)) {
            throw ValidationException::withMessages([
                'produto_aparelho_id' => ['Já existe ordem de serviço interna em aberto para este produto (sem serial). Finalize ou reprove a OS anterior.'],
            ]);
        }
        $request->merge([
            'tradein_inventory_item_id' => null,
            'produto_aparelho_id' => (int) $produto->id,
            'produto_aparelho_unico_id' => null,
        ]);
    }

    /**
     * JSON de seriais em estoque para produto tipo_unico (OS interna / formulário).
     */
    public function aparelhoInternoSeriais(Request $request)
    {
        $request->validate([
            'produto_id' => 'required|integer',
            'exceto_ordem_servico_id' => 'nullable|integer',
        ]);

        $empresaId = (int) request()->empresa_id;
        $produtoId = (int) $request->produto_id;
        $excetoOs = $request->filled('exceto_ordem_servico_id') ? (int) $request->exceto_ordem_servico_id : null;

        $produto = Produto::where('empresa_id', $empresaId)->findOrFail($produtoId);

        if (!(bool) $produto->tipo_unico) {
            return response()->json([]);
        }

        $busy = $this->queryOsInternaAbertaVinculoAparelho($empresaId, $excetoOs)
            ->whereNotNull('produto_aparelho_unico_id')
            ->pluck('produto_aparelho_unico_id');

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

    private function sincronizarStatusTradeinAssistenciaAoAbrirOs(OrdemServico $ordem): void
    {
        if (!$ordem->isOsInterna() || empty($ordem->tradein_inventory_item_id)) {
            return;
        }

        $item = TradeinInventoryItem::where('empresa_id', (int) $ordem->empresa_id)
            ->where('id', (int) $ordem->tradein_inventory_item_id)
            ->first();

        if (!$item) {
            return;
        }

        if ($item->status === TradeinInventoryItem::STATUS_PENDING_TRANSFER) {
            $item->status = TradeinInventoryItem::STATUS_EM_ASSISTENCIA;
            $item->save();
        }
    }
}
