@extends('layouts.app', ['title' => 'Painel assistência técnica'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex flex-wrap justify-content-between gap-2">
        <h4 class="mb-0">Painel de status — assistência técnica</h4>
        <div class="d-flex gap-2">
            <a href="{{ route('ordem-servico.index') }}" class="btn btn-outline-secondary btn-sm">Todas as OS</a>
            <a href="{{ route('ordem-servico.fila-tecnica') }}" class="btn btn-primary btn-sm">Fila técnica</a>
        </div>
    </div>
    <div class="card-body">
        <p class="small text-muted">Contagem apenas de OS em <strong>Pendente</strong> ou <strong>Aprovado</strong> (ativo na bancada).</p>

        <h6 class="text-secondary mt-2">Por fase na bancada</h6>
        <div class="row g-2 mb-4">
            @foreach($labelsFase as $k => $titulo)
                <div class="col-6 col-md-4 col-lg-2">
                    <div class="border rounded p-2 text-center bg-light">
                        <div class="fs-5 fw-bold">{{ (int) ($porFase[$k] ?? 0) }}</div>
                        <div class="small text-muted">{{ $titulo }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <h6 class="text-secondary">Por estado financeiro (ativos)</h6>
        <div class="mb-4 d-flex flex-wrap gap-2">
            @foreach($porEstadoFinanceiro as $code => $row)
                <span class="badge bg-secondary fs-6">
                    {{ $row['label'] }}: {{ $row['total'] }}
                </span>
            @endforeach
        </div>

        <h6 class="text-secondary">Possíveis atrasos (previsão &lt; hoje)</h6>
        @if($atrasadas->isEmpty())
            <p class="text-muted small mb-0">Nenhuma OS ativa com previsão vencida.</p>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>OS</th>
                            <th>Cliente</th>
                            <th>Equipamento</th>
                            <th>Previsão</th>
                            <th>Técnico</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($atrasadas as $o)
                            <tr>
                                <td>{{ $o->codigo_sequencial }}</td>
                                <td>{{ $o->cliente ? ($o->cliente->nome_fantasia ?: $o->cliente->razao_social) : '—' }}</td>
                                <td>{{ $o->equipamento ?? '—' }}</td>
                                <td>{{ $o->data_previsao_entrega ? __data_pt($o->data_previsao_entrega, 0) : '—' }}</td>
                                <td>{{ $o->tecnicoResponsavel ? $o->tecnicoResponsavel->nome : '—' }}</td>
                                <td>
                                    <a href="{{ route('ordem-servico.show', $o->id) }}" class="btn btn-sm btn-dark">Abrir</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
