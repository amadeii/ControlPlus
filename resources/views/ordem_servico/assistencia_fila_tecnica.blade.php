@extends('layouts.app', ['title' => 'Fila técnica — assistência'])

@section('content')
<div class="mt-1">
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
                <h4 class="mb-0">Fila técnica</h4>
                <div class="d-flex gap-2">
                    <a href="{{ route('ordem-servico.painel-assistencia') }}" class="btn btn-outline-secondary btn-sm">Painel</a>
                    <a href="{{ route('ordem-servico.index') }}" class="btn btn-outline-dark btn-sm">Lista geral</a>
                </div>
            </div>
            <p class="small text-muted mb-0">Ordens <strong>Pendente</strong> ou <strong>Aprovado</strong>, priorizando previsão de entrega. Use os filtros como na lista principal.</p>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            {!! Form::open()->fill(request()->all())->get()->route('ordem-servico.fila-tecnica') !!}
            <div class="row g-2 mt-1">
                <div class="col-md-4">
                    {!! Form::select('cliente_id', 'Cliente') !!}
                </div>
                <div class="col-md-2">
                    {!! Form::tel('codigo', 'Código OS') !!}
                </div>
                <div class="col-md-3">
                    {!! Form::select('tecnico_responsavel_id', 'Técnico responsável', ['' => 'Todos'] + $funcionariosFiltroAssistencia->pluck('nome', 'id')->all())->attrs(['class' => 'form-select']) !!}
                </div>
                <div class="col-md-3">
                    {!! Form::select('assistencia_fase_tecnica', 'Fase na bancada', ['' => 'Todos'] + $labelsFase)->attrs(['class' => 'form-select']) !!}
                </div>
                <div class="col-md-4">
                    {!! Form::text('equipamento', 'Equipamento')->attrs(['class' => 'form-control']) !!}
                </div>
                <div class="col-md-12">
                    <button class="btn btn-primary btn-sm" type="submit"><i class="ri-search-line"></i> Filtrar</button>
                    <a class="btn btn-danger btn-sm" href="{{ route('ordem-servico.fila-tecnica') }}"><i class="ri-eraser-fill"></i> Limpar</a>
                </div>
            </div>
            {!! Form::close() !!}

            <div class="table-responsive mt-3">
                <table class="table table-striped table-centered mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Código</th>
                            <th>Cliente</th>
                            <th>Equipamento</th>
                            <th>Previsão</th>
                            <th>Fase</th>
                            <th>Técnico</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($data as $item)
                            <tr>
                                <td>{{ $item->codigo_sequencial }}</td>
                                <td>{{ $item->cliente ? ($item->cliente->nome_fantasia ?: $item->cliente->razao_social) : '—' }}</td>
                                <td>{{ $item->equipamento ?: '—' }}</td>
                                <td>{{ $item->data_previsao_entrega ? __data_pt($item->data_previsao_entrega, 0) : '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $labelsFase[$item->assistencia_fase_tecnica] ?? ($item->assistencia_fase_tecnica ?: 'Na fila') }}</span></td>
                                <td>{{ $item->tecnicoResponsavel ? $item->tecnicoResponsavel->nome : '—' }}</td>
                                <td>{{ \App\Models\OrdemServico::estados()[$item->estado] ?? $item->estado }}</td>
                                <td><a href="{{ route('ordem-servico.show', $item->id) }}" class="btn btn-sm btn-dark"><i class="ri-survey-line"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhuma OS na fila com os filtros atuais.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($data->hasPages())
                <div class="mt-2">{{ $data->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
