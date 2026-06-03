@extends('layouts.app', ['title' => 'Ordem de Serviço'])
@section('css')
<style type="text/css">

</style>
@endsection
@section('content')

<div class="card mt-1">
    <div class="card-body">
        <div class="pl-lg-4">
            <div class="">
                <div class="ms">
                    <div class="row">
                        <div class="col-6">
                            <h5>Estado
                                @if($ordem->estado == 'pd')
                                <span class="badge bg-warning">PENDENTE</span>
                                @elseif($ordem->estado == 'ap')
                                <span class="badge bg-success">APROVADO</span>
                                @elseif($ordem->estado == 'rp')
                                <span class="badge bg-danger">{{ $configGeral->tipo_ordem_servico == 'assistencia técinica' ? 'SEM REPARO' : 'REPROVADO' }}</span>
                                @elseif($ordem->estado == 'fz')
                                <span class="badge bg-info">FINALIZADO</span>
                                @endif
                            </h5> 
                        </div>
                        <div class="col-6">

                            <h3 class="text-danger">OS #{{ $ordem->codigo_sequencial }}</h3>
                        </div>
                    </div>

                    @if($ordem->estado == 'fz' && sizeof($ordem->fatura) > 0)
                    <div class="row">
                        <div class="col-md-6">
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <th>Tipo de pagamento</th>
                                        <th>Data de vencimento</th>
                                        <th>Valor</th>
                                    </thead>
                                    <tbody>
                                        @foreach($ordem->fatura as $f)
                                        <tr>
                                            <td>{{ $f->getTipoPagamento() }}</td>
                                            <td>{{ __data_pt($f->data_vencimento, 0) }}</td>
                                            <td>{{ __moeda($f->valor) }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <th colspan="2">Soma</th>
                                            <th>R$ {{ __moeda($ordem->fatura->sum('valor')) }}</th>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>
                    <hr>
                    @endif

                    <div class="mt-" style="text-align: right;">
                        <a href="{{ url()->previous() }}" class="btn btn-danger btn-sm px-3">
                            <i class="ri-arrow-left-double-fill"></i>Voltar
                        </a>
                    </div>
                    <div class="row mb-2 mt-2">
                        <div class="col-md-3 col-6">
                            <h5>Início: <strong class="text-primary">{{ __data_pt($ordem->data_inicio) }}</strong></h5>
                        </div>
                        <div class="col-md-3 col-6 text-center">
                            <h5>Total: <strong class="text-primary">R$ {{ __moeda($ordem->valor) }}</strong> </h5>
                        </div>
                        <div class="col-md-3 col-6 text-center">
                            <h5>Atendente: <strong class="text-primary">{{ $ordem->funcionario ? $ordem->funcionario->nome : '--' }}</strong></h5>
                        </div>
                        <div class="col-md-3 col-6 text-end">
                            <h5>Usuário: <strong class="text-primary">{{ $ordem->usuario->name }}</strong></h5>
                        </div>
                    </div>

                    @if($configGeral->tipo_ordem_servico == 'assistencia técinica')
                    <div class="row mb-3 g-2">
                        <div class="col-md-4 col-6">
                            <h6 class="text-muted mb-0">Técnico responsável</h6>
                            <strong class="text-primary">{{ $ordem->tecnicoResponsavel ? $ordem->tecnicoResponsavel->nome : '—' }}</strong>
                        </div>
                        <div class="col-md-4 col-6">
                            <h6 class="text-muted mb-0">Previsão de entrega</h6>
                            <strong>{{ $ordem->data_previsao_entrega ? __data_pt($ordem->data_previsao_entrega, 0) : '—' }}</strong>
                            <small class="d-block text-muted">Data efetiva de entrega continua no campo &quot;Data de entrega&quot; abaixo.</small>
                        </div>
                        <div class="col-md-4 col-12">
                            <h6 class="text-muted mb-0">Fase na bancada</h6>
                            <span class="badge bg-secondary fs-6">
                                {{ \App\Models\OrdemServico::assistenciaFasesTecnicas()[$ordem->assistencia_fase_tecnica] ?? ($ordem->assistencia_fase_tecnica ?: 'Na fila') }}
                            </span>
                        </div>
                    </div>
                    @endif

                    @if($configGeral->tipo_ordem_servico == 'assistencia técinica')
                    <div class="row mb-2">
                        <div class="col-md-4 col-12">
                            <h5>Equipamento: <strong class="text-info">{{ $ordem->equipamento }}</strong></h5>
                        </div>
                        <div class="col-md-4 col-12 text-center">
                            <h5>Número de série: <strong class="text-info">{{ $ordem->numero_serie ?? '--' }}</strong></h5>
                        </div>
                        <div class="col-md-4 col-12 text-end">
                            <h5>Cor: <strong class="text-muted">{{ $ordem->cor ?? '--' }}</strong></h5>
                        </div>
                    </div>

                    <div class="row mb-3 g-2">
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <h5 class="text-secondary border-bottom pb-2">Checklist físico de entrada</h5>
                                    @if($ordem->assistenciaChecklistFisicoItens && $ordem->assistenciaChecklistFisicoItens->isNotEmpty())
                                    <div class="table-responsive">
                                        <table class="table table-sm mb-0">
                                            <thead>
                                                <tr>
                                                    <th>Item</th>
                                                    <th>Estado</th>
                                                    <th>Observação</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($ordem->assistenciaChecklistFisicoItens as $chkFisico)
                                                <tr>
                                                    <td>{{ $chkFisico->titulo }}</td>
                                                    <td>{{ \App\Models\OrdemServico::assistenciaChecklistFisicoEstados()[$chkFisico->estado] ?? $chkFisico->estado }}</td>
                                                    <td>{{ $chkFisico->observacao ?: '—' }}</td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                    @else
                                    <p class="text-muted mb-0">Sem checklist físico registrado. OS possivelmente anterior ao fluxo obrigatório.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border h-100">
                                <div class="card-body">
                                    <h5 class="text-secondary border-bottom pb-2">Documentos e fotos</h5>
                                    @if($ordem->documentos && $ordem->documentos->isNotEmpty())
                                    <p class="mb-2">
                                        @foreach($ordem->documentos as $docOs)
                                        <a class="btn btn-outline-primary btn-sm mb-1" target="_blank" href="{{ route('ordem-servico.download-documento', [$docOs->id, 'empresa_id' => request()->empresa_id]) }}">
                                            {{ $docOs->tipo === 'entrada' ? 'Termo de entrada' : 'Termo final' }}
                                        </a>
                                        @endforeach
                                    </p>
                                    @else
                                    <p class="text-muted">Nenhum documento persistido.</p>
                                    @endif

                                    @if($ordem->anexos && $ordem->anexos->isNotEmpty())
                                    <div class="d-flex flex-wrap gap-2">
                                        @foreach($ordem->anexos as $anexoOs)
                                        <a target="_blank" href="{{ asset($anexoOs->caminho) }}" class="text-decoration-none">
                                            <img src="{{ asset($anexoOs->caminho) }}" alt="{{ $anexoOs->tipo }}" style="width: 72px; height: 72px; object-fit: cover; border-radius: 6px; border: 1px solid #ddd;">
                                            <small class="d-block text-center">{{ $anexoOs->tipo }}</small>
                                        </a>
                                        @endforeach
                                    </div>
                                    @else
                                    <p class="text-muted mb-0">Sem fotos anexadas.</p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>

                    @if(isset($assistenciaControleTimeline) && $assistenciaControleTimeline !== null)
                        @include('ordem_servico.partials.assistencia_controle_tecnico')
                    @endif

                    @can('tradein_view')
                    @if($ordem->tradein_inventory_item_id && $ordem->tradeinInventoryItem)
                    <div class="row mb-3">
                        @php
                            $lOs = $ordem->tradeinCustoPecaLancamentos ?? collect();
                            $custoOsAntesDasPecas = $lOs->isNotEmpty() ? ($lOs->first()->custo_aparelho_antes ?? null) : null;
                        @endphp
                        <div class="col-12">
                            <h5 class="text-secondary border-bottom pb-2">Trade-in na assistência</h5>
                            <p class="mb-1">
                                <strong>Inventário #{{ $ordem->tradeinInventoryItem->id }}</strong>
                                @if($ordem->tradeinInventoryItem->descricao_item)
                                    — {{ $ordem->tradeinInventoryItem->descricao_item }}
                                @endif
                                @if($ordem->tradeinInventoryItem->serial)
                                    | Serial {{ $ordem->tradeinInventoryItem->serial }}
                                @endif
                            </p>
                            <p class="mb-2 small">
                                @if(!is_null($custoOsAntesDasPecas))
                                    Custo do aparelho antes das peças nesta OS: <strong>R$ {{ __moeda($custoOsAntesDasPecas) }}</strong> ·
                                @endif
                                Custo atual (inventário): <strong>R$ {{ __moeda($ordem->tradeinInventoryItem->valor ?? 0) }}</strong>
                                @if($lOs->isNotEmpty() && $lOs->first()->valor_avaliado_tradein_origem !== null)
                                    · Avaliado do trade-in (snapshot): <strong>R$ {{ __moeda($lOs->first()->valor_avaliado_tradein_origem) }}</strong>
                                @endif
                                <a href="{{ route('tradein.inventory.edit', ['id' => $ordem->tradeinInventoryItem->id, 'empresa_id' => request()->empresa_id]) }}" class="btn btn-sm btn-outline-secondary ms-2">
                                    Abrir inventário
                                </a>
                            </p>
                        </div>
                        @if($lOs->isNotEmpty())
                        <div class="col-12 table-responsive mt-2">
                            <table class="table table-sm table-bordered caption-top">
                                <caption class="small text-muted">Peças que incrementaram custo nesta OS</caption>
                                <thead class="table-light">
                                    <tr>
                                        <th>Peça</th>
                                        <th>Qtd</th>
                                        <th>Incremento</th>
                                        <th>Custo antes</th>
                                        <th>Custo depois</th>
                                        <th>Usuário</th>
                                        <th>Lançamento</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($lOs as $l)
                                    <tr>
                                        <td>{{ $l->peca ? $l->peca->nome : '—' }}</td>
                                        <td>{{ $l->quantidade_peca }}</td>
                                        <td>R$ {{ __moeda($l->valor_custo_incremento) }}</td>
                                        <td>R$ {{ __moeda($l->custo_aparelho_antes) }}</td>
                                        <td>R$ {{ __moeda($l->custo_aparelho_depois) }}</td>
                                        <td>{{ $l->user ? $l->user->name : '—' }}</td>
                                        <td>{{ __data_pt($l->created_at ?? '') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @endif
                    </div>
                    @endif
                    @endcan
                    @endif

                    <a title="Link para Cliente da OS" target="_blank" class="btn btn-dark btn-sm" href="{{ route('ordem-servico.link', $ordem->hash_link) }}">
                        <i class="ri-links-fill"></i> Link da ordem de serviço
                    </a>

                    <a href="{{ route('ordem-servico.alterar-estado', [$ordem->id]) }}" class="btn btn-info btn-sm" href=""><i class="ri-refresh-line"></i>
                        Alterar estado
                    </a>
                    <!-- <a target="_blank" class="btn btn-primary btn-sm" href="{{ route('ordem-servico.imprimir', $ordem->id) }}"><i class="ri-printer-line"></i>
                        Imprimir
                    </a> -->
                    <a target="_blank" class="btn btn-primary btn-sm" onclick="imprimir('{{ $ordem->id }}')"><i class="ri-printer-line"></i>
                        Imprimir
                    </a>
                    @if($ordem->nfe_id == 0)
                    <a class="btn btn-success btn-sm" href="{{ route('ordem-servico.gerar-nfe', $ordem->id) }}">
                        <i class="ri-file-text-line"></i>
                        Gerar NFe
                    </a>
                    @endif

                    @if($ordem->oticaOs)
                    <a class="btn btn-warning btn-sm" href="{{ route('ordem-servico.edit', $ordem->id) }}">
                        <i class="ri-edit-line"></i>
                        Editar receita
                    </a>
                    @endif

                    
                </div>

                <form method="post" class="row g-2 mt-3" action="{{ route('ordem-servico.update-entrega', [$ordem->id])}}">
                    @csrf
                    @method('put')
                    <div class="col-md-2">
                        {!! Form::tel('adiantamento', 'Valor de adiantamento')->attrs(['class' => 'moeda'])
                        ->value(__moeda($ordem->adiantamento)) !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::date('data_entrega', 'Data de entrega')->value(substr($ordem->data_entrega,0, 10)) !!}
                    </div>
                    @if(!__isSegmentoPlanoOtica() && $configGeral->tipo_ordem_servico == 'oficina')
                    <div class="col-md-2">
                        {!! Form::select('veiculo_id', 'Veículo', ['' => 'Selecione'] + $veiculos->pluck('info', 'id')->all())
                        ->attrs(['class' => 'select2'])->id('veiculo_id')
                        ->value($ordem->veiculo_id) !!}
                    </div>
                    @endif
                    
                    <div class="col-md-2">
                        <br>
                        <button class="btn btn-success" href="{{ route('ordem-servico.gerar-nfe', $ordem->id) }}">
                            Salvar
                        </button>
                    </div>
                </form>
            </div>
            <hr class="">
            <div class="card border row">
                {!! Form::open()
                ->post()
                ->route('ordem-servico.store-servico')!!}
                <h3 class="m-3">Serviços</h3>
                <div class="row m-2 mt-3">
                    <input type="hidden" value="{{$ordem->id}}" name="ordem_servico_id">
                    <div class="col-md-4">
                        {!! Form::select('servico_id', 'Serviço', [null => 'Selecione'] + $servicos->pluck('nome', 'id')->all())->attrs(['class' => 'form-select'])->required() !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::tel('quantidade', 'Quantidade')->attrs(['class' => 'moeda'])->required() !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::text('valor', 'Valor unitário')->attrs(['class' => 'moeda'])->required() !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::select('status', 'Status', [0 => 'Pendente', 1 => 'Finalizado'])->attrs(['class' => 'form-select'])->required() !!}
                    </div>
                    <div class="col-md-2">
                        <br>
                        <button type="submit" class="btn btn-success">
                            <i class="ri-add-line"></i>Adicionar
                        </button>

                    </div>
                </div>
                {!! Form::close() !!}
                <div class="card-body">
                    <div class="table-responsive">
                        <p class="">total de serviços: <strong>{{ sizeof($ordem->servicos) }}</strong></p>
                        <table class="table mb-0 table-striped table-servico">
                            <thead class="table-dark">
                                <tr>
                                    <th>Serviço</th>
                                    <th>Quantidade</th>
                                    <th>Status</th>
                                    <th>Subtotal</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @isset($ordem)
                                @forelse($ordem->servicos as $item)
                                <tr>
                                    <td>
                                        <input style="width: 700px" readonly type="text" name="servico[]" class="form-control" value="{{ $item->servico->nome }}">
                                    </td>
                                    <td>
                                        <input readonly type="tel" name="servico_quantidade[]" class="form-control" value="{{ $item->quantidade }}">
                                    </td>
                                    <td>
                                        @if($item->status)
                                        <span class="badge bg-success">FINALIZADO
                                        </span>
                                        @else
                                        <span class="badge bg-warning">PENDENTE
                                        </span>
                                        @endif
                                    </td>
                                    <td>
                                        <input readonly type="tel" name="valor[]" class="form-control qtd-item" value="{{ __moeda($item->subtotal) }}">
                                    </td>
                                    <td>
                                        <form action="{{ route('ordem-servico.deletar-servico', $item->id) }}" method="post" id="form-servico-{{$item->id}}">
                                            @method('delete')
                                            @csrf
                                            <a title="Alterar estado" href="{{ route('ordem-servico.alterar-status-servico', $item->id) }}" class="btn btn-sm btn-dark">
                                                <i class="ri-refresh-line"></i>
                                            </a>

                                            <button type="button" class="btn btn-delete btn-sm btn-danger">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center">Nenhum registro</td>
                                </tr>
                                @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
            <div class="card border row">
                {!! Form::open()
                ->post()
                ->route('ordem-servico.store-produto')!!}
                <h3 class="m-3">Produtos / peças</h3>
                <div class="row m-2">
                    <input type="hidden" value="{{$ordem->id}}" name="ordem_servico_id">
                    <div class="col-md-4">
                        <label class="form-label">Produto do cadastro <span class="text-muted">(opcional)</span></label>
                        <select name="produto_id" id="inp-produto_id" class="form-select select2" data-toggle="select2">
                            <option value="">— Texto livre (sem produto cadastrado) —</option>
                        </select>
                        <small class="text-muted">Se deixar vazio, preencha a descrição manual abaixo. Sem cadastro não há baixa de estoque.</small>
                    </div>
                    <div class="col-md-2">
                        {!! Form::tel('quantidade_produto', 'Quantidade')->attrs(['class' => 'moeda', 'id' => 'inp-quantidade_produto'])->required() !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::tel('valor_produto', 'Valor unitário')->attrs(['class' => 'moeda', 'id' => 'inp-valor_produto'])->required() !!}
                    </div>
                    <div class="col-12 mt-2" id="bloco-produto-manual-os" style="display: none;">
                        <div class="row g-2">
                            <div class="col-md-5">
                                {!! Form::textarea('descricao_livre', 'Descrição (texto livre)')->attrs(['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Ex.: Display original, flex de carga...']) !!}
                            </div>
                            <div class="col-md-2">
                                {!! Form::text('marca_livre', 'Marca (linha)')->attrs(['class' => 'form-control']) !!}
                            </div>
                            <div class="col-md-2">
                                {!! Form::text('modelo_livre', 'Modelo (linha)')->attrs(['class' => 'form-control']) !!}
                            </div>
                            <div class="col-md-3">
                                {!! Form::text('imei_serial_livre', 'IMEI / série (linha)')->attrs(['class' => 'form-control']) !!}
                            </div>
                        </div>
                    </div>
                    @if(!empty($mostrarSelectDepositoOsPeca) && $mostrarSelectDepositoOsPeca)
                    <div class="col-md-4 mt-2 mt-md-0 d-none" id="bloco-deposito-prod-os">
                        {!! Form::select('deposito_os_peca_id', 'Depósito da baixa (estoque)', $depositosPecaOsOpcoes ?? ['' => 'Padrão (local da OS ou ativo)'])->attrs(['class' => 'form-select']) !!}
                    </div>
                    @endif
                    <div class="col-md-2">
                        <br>
                        @if(!isset($not_submit))
                        <button type="submit" class="btn btn-success"><i class=" ri-add-line"></i>Adicionar</button>
                        @endif
                    </div>
                </div>
                {!! Form::close() !!}
                <div class="card-body">
                    @php
                        $ehOsTradeinInterna = $ordem->isOsInterna() && !empty($ordem->tradein_inventory_item_id);
                        $pendenciasPecaOs = $ehOsTradeinInterna
                            ? $ordem->itens->pluck('assistenciaPecaBaixa')->filter()
                            : collect();
                        $qtdPendentesPecaOs = $pendenciasPecaOs->where('status', \App\Models\AssistenciaOsPecaBaixa::STATUS_PENDENTE)->count();
                        $qtdBaixadasPecaOs = $pendenciasPecaOs->where('status', \App\Models\AssistenciaOsPecaBaixa::STATUS_BAIXADO)->count();
                    @endphp

                    @if($ehOsTradeinInterna)
                    <div class="alert alert-warning border mb-3">
                        <div class="d-flex flex-wrap justify-content-between gap-2">
                            <div>
                                <strong>Fluxo Trade-In com baixa tardia.</strong>
                                Antes da aprovação administrativa, a peça fica apenas apontada na OS.
                            </div>
                            <div class="text-nowrap">
                                <span class="badge bg-warning text-dark">Pendentes: {{ $qtdPendentesPecaOs }}</span>
                                <span class="badge bg-success ms-1">Baixadas: {{ $qtdBaixadasPecaOs }}</span>
                            </div>
                        </div>
                    </div>
                    @endif
                    <div class="table-responsive">
                        <p class="">total de produtos: <strong>{{ sizeof($ordem->itens) }}</strong></p>
                        <table class="table mb-0 table-striped table-produto">
                            <thead class="table-dark">
                                <tr>
                                    <th>Produto</th>
                                    <th>Quantidade</th>
                                    <th>Valor</th>
                                    <th>SubTotal</th>
                                    @if($ehOsTradeinInterna)
                                    <th>Status operacional</th>
                                    @endif
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @isset($ordem)
                                @forelse ($ordem->itens  as $item)
                                @php
                                    $pendenciaBaixa = $item->assistenciaPecaBaixa;
                                @endphp
                                <tr>
                                    <td>
                                        <input style="width: 700px" readonly type="text" name="produto[]" class="form-control" value="{{ $item->descricaoLinha() }}">
                                    </td>
                                    <td>
                                        <input readonly type="tel" name="produto_quantidade[]" class="form-control" value="{{ $item->quantidade }}">
                                    </td>

                                    <td>
                                        <input readonly type="tel" name="total[]" class="form-control qtd-item" value="{{ __moeda($item->quantidade != 0 ? ($item->subtotal / $item->quantidade) : 0) }}">
                                    </td>
                                    <td>
                                        <input readonly type="tel" name="subtotal[]" class="form-control qtd-item" value="{{ __moeda($item->subtotal) }}">
                                    </td>
                                    @if($ehOsTradeinInterna)
                                    <td style="min-width: 240px;">
                                        @if($pendenciaBaixa)
                                        <span class="badge {{ $pendenciaBaixa->statusBadgeClass() }}">{{ $pendenciaBaixa->statusLabel() }}</span>
                                        <small class="d-block text-muted mt-1">{{ $pendenciaBaixa->statusResumoOperacional() }}</small>
                                        @if($pendenciaBaixa->status === \App\Models\AssistenciaOsPecaBaixa::STATUS_BAIXADO && $pendenciaBaixa->baixado_em)
                                        <small class="d-block text-success">
                                            Baixada em {{ __data_pt($pendenciaBaixa->baixado_em, 0) }}
                                            @if($pendenciaBaixa->aprovadoPor)
                                            por {{ $pendenciaBaixa->aprovadoPor->name }}
                                            @endif
                                        </small>
                                        @endif
                                        @else
                                        <span class="badge bg-secondary">Sem baixa de estoque</span>
                                        <small class="d-block text-muted mt-1">
                                            {{ $item->produto_id ? 'Linha sem pendência rastreada neste fluxo.' : 'Linha livre sem produto cadastrado, sem consumo de estoque.' }}
                                        </small>
                                        @endif
                                    </td>
                                    @endif
                                    <td>
                                        <form action="{{ route('ordem-servico.deletar-produto', $item->id) }}" method="post" id="form-{{$item->id}}">
                                            @method('delete')
                                            
                                            @csrf
                                            <button type="button" class="btn btn-delete btn-sm btn-danger">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="{{ $ehOsTradeinInterna ? 6 : 5 }}" class="text-center">Nenhum registro</td>
                                </tr>
                                @endforelse
                                @endif
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <hr class="mt-3">
            <div class="card border row">

                <h3 class="text m-3">Relatórios</h3>
                <div class="row m-2">
                    <div class="col-md-3">
                        <a href="{{ route('ordem-servico.add-relatorio', $ordem->id) }}" class="btn btn-success"><i class=" ri-add-line"></i>Adicionar relatório</a>
                    </div>
                    <p class="mt-2">total de relatórios: <strong>{{ sizeof($ordem->relatorios) }}</strong></p>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table mb-0 table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Data</th>
                                    <th>Usuário</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($ordem->relatorios as $item)
                                <tr>
                                    <td>{{ __data_pt($item->created_at) }}</td>
                                    <td>{{ $item->usuario->name }}</td>
                                    <td>
                                        <form action="{{ route('ordem-servico.delete-relatorio', $item->id) }}" method="post" id="form-relatorio-{{$item->id}}">
                                            @method('delete')
                                            @csrf
                                            <a href="{{ route('ordem-servico.edit-relatorio', $item->id) }}" title="Editar" class="btn btn-warning btn-sm text-white">
                                                <i class="ri-pencil-fill"></i>
                                            </a>

                                            <button type="button" class="btn btn-delete btn-sm btn-danger">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>

                                            <button type="button" class="btn btn-dark btn-sm" onclick="modalRelatorio('{{ $item->id }}', '{{ $item->texto }}')">
                                                <i class="ri-file-line"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>

                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border row">
                <h3 class="text m-3">Descrição/Observação</h3>

                <form method="post" action="{{ route('ordem-servico.update-descricao', [$ordem->id]) }}">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="col-md-12">
                            {!!Form::textarea('descricao', 'Descrição/Observação')
                            ->attrs(['rows' => '6', 'class' => 'tiny'])
                            ->value($ordem->descricao)
                            !!}
                        </div>

                        <div class="col-12 mt-2" style="text-align: right;">
                            <button type="submit" class="btn btn-success px-5" id="btn-store">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>

            @if($configGeral->tipo_ordem_servico == 'assistencia técinica')

            <div class="card border row">
                <div class="m-3">
                    <label>Diagnóstico do cliente</label>
                    {!! $ordem->diagnostico_cliente !!}
                </div>
            </div>
            <div class="card border row">
                <h3 class="m-3">Diagnóstico técnico</h3>

                <form method="post" action="{{ route('ordem-servico.update-diagnostico', [$ordem->id]) }}">
                    @csrf
                    @method('put')
                    <div class="card-body">
                        <div class="col-md-12">
                            {!!Form::textarea('diagnostico_tecnico', 'Descrição do diagnóstico')
                            ->attrs(['rows' => '6', 'class' => 'tiny'])
                            ->value($ordem->diagnostico_tecnico)
                            !!}
                        </div>

                        <div class="col-12 mt-2" style="text-align: right;">
                            <button type="submit" class="btn btn-success px-5" id="btn-store">Salvar</button>
                        </div>
                    </div>
                </form>
            </div>

            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="modal-relatorio" tabindex="-1" aria-labelledby="" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Relatório</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

@section('js')
<script type="text/javascript" src="/js/ordem_servico.js"></script>
<script src="/tinymce/tinymce.min.js"></script>
<script type="text/javascript">
    $(function(){
        tinymce.init({ selector: 'textarea.tiny', language: 'pt_BR'})

        setTimeout(() => {
            $('.tox-promotion, .tox-statusbar__right-container').addClass('d-none')
        }, 500)
    })

    function modalRelatorio(id, texto){
        $('#modal-relatorio').modal('show')
        $('#modal-relatorio .modal-body').html(texto)
    }

    (function () {
        function osToggleProdutoManual() {
            var sel = $('#inp-produto_id');
            var v = sel.val();
            var manual = $('#bloco-produto-manual-os');
            var dep = $('#bloco-deposito-prod-os');
            if (!v || v === '') {
                manual.show();
                if (dep.length) dep.addClass('d-none');
            } else {
                manual.hide();
                if (dep.length) dep.removeClass('d-none');
            }
        }
        $('#inp-produto_id').on('change.select2os', osToggleProdutoManual);
        setTimeout(osToggleProdutoManual, 500);
    })();
</script>
@endsection
