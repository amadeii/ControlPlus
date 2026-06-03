@php
    $mostrarListaAssistencia = isset($configGeral) && $configGeral->tipo_ordem_servico === 'assistencia técinica';
@endphp
@extends('layouts.app', ['title' => 'Ordens de serviço'])
@section('content')
<div class="mt-1">
    <div class="row">
        <div class="card">
            <div class="card-body">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                    <h4 class="mb-0">Ordens de serviço</h4>
                </div>
                @if($mostrarListaAssistencia)
                <div class="alert alert-info py-2 small mb-3 mb-md-2">
                    Com o tipo da OS em <strong>Assistência técnica</strong>, use os filtros de <strong>equipamento</strong> e <strong>número de série</strong> (também buscam <strong>marca/modelo</strong> no cabeçalho e texto nas linhas). A inclusão de <strong>peças</strong> com produto do <strong>cadastro</strong> gera baixa de estoque quando o produto gerencia estoque; linhas <strong>só em texto</strong> não baixam estoque.
                </div>
                @endif
                <div class="col-md-2">
                    @can('ordem_servico_create')
                    <a href="{{ route('ordem-servico.create') }}" class="btn btn-success">
                        <i class="ri-add-circle-fill"></i>
                        Nova Ordem de Serviço
                    </a>
                    @endcan
                </div>
                <hr class="mt-3">
                <div class="col-lg-12">
                    {!! Form::open()->fill(request()->all())->get()->route('ordem-servico.index') !!}
                    <div class="row mt-3 g-2">
                        <div class="col-md-4">
                            {!!Form::select('cliente_id', 'Pesquisar por cliente')
                            !!}
                        </div>
                        <div class="col-md-2">
                            {!!Form::date('start_date', 'Data de início')
                            !!}
                        </div>
                        <div class="col-md-2">
                            {!!Form::date('end_date', 'Data de fim')
                            !!}
                        </div>
                        <div class="col-md-2">
                            {!!Form::tel('codigo', 'Código')
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('estado', 'Estado', ['' => 'Todos'] + \App\Models\OrdemServico::estados())
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        @if($mostrarListaAssistencia)
                        <div class="col-md-4">
                            {!! Form::text('equipamento', 'Equipamento')->attrs(['class' => 'form-control']) !!}
                        </div>
                        <div class="col-md-2">
                            {!! Form::text('numero_serie', 'Número de série')->attrs(['class' => 'form-control']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::select(
                                'tecnico_responsavel_id',
                                'Técnico responsável',
                                ['' => 'Todos'] + $funcionariosFiltroAssistencia->pluck('nome', 'id')->all()
                            )->attrs(['class' => 'form-select']) !!}
                        </div>
                        <div class="col-md-3">
                            {!! Form::select(
                                'assistencia_fase_tecnica',
                                'Fase na bancada',
                                ['' => 'Todas'] + \App\Models\OrdemServico::assistenciaFasesTecnicas()
                            )->attrs(['class' => 'form-select']) !!}
                        </div>
                        @endif
                        @if(!__isSegmentoPlanoOtica() && optional($configGeral)->tipo_ordem_servico == 'oficina')
                        <div class="col-md-2">
                            {!!Form::select('veiculo_id', 'Veículo', ['' => 'Selecione'] + $veiculos->pluck('info', 'id')->all())
                            ->attrs(['class' => 'select2'])
                            ->id('veiculo_id')
                            !!}
                        </div>
                        @endif
                        @if(__isSegmentoPlanoOtica())
                        <div class="col-md-2">
                            {!!Form::select('convenio_id', 'Convênio', ['' => 'Selecione'] + $convenios->pluck('nome', 'id')->all())
                            ->attrs(['class' => 'form-select'])
                            ->id('convenio')
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('situacao_entrega', 'Situação de entrega', ['' => 'Selecione', '1' => 'Entregue', '-1' => 'Não entregue'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('adiantamento', 'Adiantamento', ['' => 'Selecione', '1' => 'Com adiantamento', '-1' => 'Sem adiantamento'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>
                        @endif
                        <div class="col-md-2 text-left">
                            <br>
                            <button class="btn btn-primary" type="submit"> <i class="ri-search-line"></i>Pesquisar</button>
                            <a id="clear-filter" class="btn btn-danger" href="{{ route('ordem-servico.index') }}"><i class="ri-eraser-fill"></i>Limpar</a>
                        </div>
                    </div>
                    {!!Form::close()!!}
                </div>
                <div class="col-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-striped table-centered mb-0">
                            <thead class="table-dark">
                                <tr>
                                    @can('ordem_servico_delete')
                                    <th>
                                        <div class="form-check form-checkbox-danger mb-2">
                                            <input class="form-check-input" type="checkbox" id="select-all-checkbox">
                                        </div>
                                    </th>
                                    @endcan
                                    <th>Código</th>
                                    <th>Nome</th>
                                    @if($mostrarListaAssistencia)
                                    <th>Equipamento</th>
                                    <th>Nº série</th>
                                    <th>Previsão</th>
                                    <th>Fase bancada</th>
                                    <th>Técnico</th>
                                    @endif
                                    <th>Data de início</th>
                                    <th>Data de entrega</th>
                                    <th>Valor</th>
                                    <th>Veículo</th>
                                    <th>Estado</th>
                                    <th>Situação de entrega</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($data as $item)
                                <tr>
                                    @can('ordem_servico_delete')
                                    <td data-label="Selecionar">
                                        <div class="form-check form-checkbox-danger mb-2">
                                            <input class="form-check-input check-delete" type="checkbox" name="item_delete[]" value="{{ $item->id }}">
                                        </div>
                                    </td>
                                    @endcan

                                    <td data-label="Código">{{ $item->codigo_sequencial }}</td>
                                    <td data-label="Nome">{{ $item->cliente->razao_social }}</td>
                                    @if($mostrarListaAssistencia)
                                    <td data-label="Equipamento">{{ $item->equipamento ?: '—' }}</td>
                                    <td data-label="Nº série">{{ $item->numero_serie ?: '—' }}</td>
                                    <td data-label="Previsão">{{ $item->data_previsao_entrega ? __data_pt($item->data_previsao_entrega, 0) : '' }}</td>
                                    <td data-label="Fase">
                                        <span class="badge bg-secondary">
                                            {{ \App\Models\OrdemServico::assistenciaFasesTecnicas()[$item->assistencia_fase_tecnica] ?? ($item->assistencia_fase_tecnica ?: 'Na fila') }}
                                        </span>
                                    </td>
                                    <td data-label="Técnico">{{ $item->tecnicoResponsavel ? $item->tecnicoResponsavel->nome : '—' }}</td>
                                    @endif
                                    <td data-label="Data de início">{{ __data_pt($item->data_inicio, 1) }}</td>
                                    <td data-label="Data de entrega">{{ $item->data_entrega ? __data_pt($item->data_entrega, 0) : '' }}</td>
                                    <td data-label="Valor">{{ __moeda($item->valor) }}</td>
                                    <td data-label="Veículo">{{ $item->veiculo ? $item->veiculo->info : '--' }}</td>

                                    <td data-label="Estado">
                                        @if($item->estado == 'pd')
                                        <span class="badge bg-warning">PENDENTE</span>
                                        @elseif($item->estado == 'ap')
                                        <span class="badge bg-success">APROVADO</span>
                                        @elseif($item->estado == 'rp')
                                        <span class="badge bg-danger">REPROVADO</span>
                                        @elseif($item->estado == 'fz')
                                        <span class="badge bg-info">FINALIZADO</span>
                                        @endif
                                    </td>

                                    <td data-label="Situação de entrega">
                                        @if($item->data_entrega != '')
                                        <i class="ri-checkbox-circle-fill text-success"></i>
                                        @else
                                        <i class="ri-close-circle-fill text-danger"></i>
                                        @endif
                                    </td>

                                    <td>
                                        <form style="width: 180px;" action="{{ route('ordem-servico.destroy', $item->id) }}" method="post" id="form-{{$item->id}}">
                                            @method('delete')
                                            @csrf

                                            @can('ordem_servico_edit')
                                            <a class="btn btn-warning btn-sm" href="{{ route('ordem-servico.edit', [$item->id]) }}">
                                                <i class="ri-pencil-fill"></i>
                                            </a>
                                            @endcan

                                            @if($item->estado == 'pd' || $item->estado == 'rp')
                                            @can('ordem_servico_delete')
                                            <button type="button" class="btn btn-delete-os-audit btn-sm btn-danger">
                                                <i class="ri-delete-bin-line"></i>
                                            </button>
                                            @endcan
                                            @endif

                                            <a title="Visualizar" href="{{ route('ordem-servico.show', $item->id) }}" class="btn btn-dark btn-sm text-white">
                                                <i class="ri-survey-line"></i>
                                            </a>

                                            <a class="btn btn-primary btn-sm" href="{{ route('ordem-servico.duplicar', [$item->id]) }}" title="Duplicar OS">
                                                <i class="ri-file-copy-line"></i>
                                            </a>

                                            @if(__isSegmentoPlanoOtica())
                                            <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="ri-printer-line"></i>
                                                <span class="caret"></span>
                                            </button>
                                            <div class="dropdown-menu">
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'via_cliente=1']) }}">Imprimir via do cliente</a>
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'via_laboratorio=1']) }}">Imprimir via do laboratório</a>
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'os=1']) }}">Imprimir OS</a>
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'os=1', 'via_cliente=1']) }}">Imprimir OS + via do cliente</a>
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'os=1', 'via_laboratorio=1']) }}">Imprimir OS + via do laboratório</a>
                                                <a target="_blank" class="dropdown-item" href="{{ route('ordem-servico.print-otica', ['ordem_servico_id='.$item->id, 'via_cliente=1', 'via_laboratorio=1']) }}">Via do cliente + via do laboratório</a>
                                            </div>
                                            @endif
                                        </form>
                                    </td>
                                </tr>

                                @empty
                                <tr>
                                    <td colspan="@can('ordem_servico_delete'){{ $mostrarListaAssistencia ? 12 : 10 }}@else{{ $mostrarListaAssistencia ? 11 : 9 }}@endcan" class="text-center">Nada encontrado</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>

                        <br>
                        @can('ordem_servico_delete')
                        <form action="{{ route('ordem-servico.destroy-select') }}" method="post" id="form-delete-select">
                            @method('delete')
                            @csrf
                            <div></div>
                            <button type="button" class="btn btn-danger btn-sm btn-delete-all btn-delete-os-bulk-audit" disabled>
                                <i class="ri-close-circle-line"></i> Remover selecionados
                            </button>
                        </form>
                        @endcan
                    </div>
                </div>
                <br>
                {!! $data->appends(request()->all())->links() !!}
            </div>
        </div>
    </div>
</div>
@can('ordem_servico_delete')
<div class="modal fade" id="modal-exclusao-os-audit" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Motivo da exclusão (auditoria)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-2">Informe um motivo com pelo menos 10 caracteres. O registro fica salvo junto ao IP e à sessão do usuário.</p>
                <textarea class="form-control" id="motivo-exclusao-os-field" rows="4" maxlength="2000"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="modal-exclusao-os-confirm">Confirmar exclusão</button>
            </div>
        </div>
    </div>
</div>
@endcan
@endsection

@section('js')
<script type="text/javascript" src="/js/delete_selecionados.js"></script>
@can('ordem_servico_delete')
<script>
(function () {
    var modalEl = document.getElementById('modal-exclusao-os-audit');
    if (!modalEl) return;
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var $pendingForm = null;

    function attachMotivo($form, motivo) {
        $form.find('input[name="motivo_auditoria_os"]').remove();
        $form.append($('<input>', {type: 'hidden', name: 'motivo_auditoria_os', value: motivo}));
    }

    $('body').on('click', '.btn-delete-os-audit', function (e) {
        e.preventDefault();
        var $form = $(this).closest('form');
        if (!$form.length || !$form.attr('id')) return;
        $pendingForm = $form;
        $('#motivo-exclusao-os-field').val('');
        modal.show();
    });

    $('#modal-exclusao-os-confirm').on('click', function () {
        var t = $('#motivo-exclusao-os-field').val().trim();
        if (t.length < 10) {
            alert('Motivo obrigatório: mínimo 10 caracteres.');
            return;
        }
        if (!$pendingForm || !$pendingForm.length) return;
        attachMotivo($pendingForm, t);
        $pendingForm.get(0).submit();
        $pendingForm = null;
        modal.hide();
    });

    $('#form-delete-select .btn-delete-os-bulk-audit').off('click');

    $('body').on('click', '#form-delete-select .btn-delete-os-bulk-audit', function (e) {
        e.preventDefault();
        var $form = $('#form-delete-select');
        var n = $form.find('input[name="item_delete[]"]').length;
        if (n === 0) return;
        $pendingForm = $form;
        $('#motivo-exclusao-os-field').val('');
        modal.show();
    });
})();
</script>
@endcan
@endsection
