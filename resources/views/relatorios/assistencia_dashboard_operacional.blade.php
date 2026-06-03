@extends('layouts.app', ['title' => 'Painel assistência'])

@section('content')
<div class="mt-2">
    <div class="row mb-3">
        <div class="col-lg-10">
            <h4 class="mb-2">Painel operacional — assistência</h4>
            <p class="text-muted mb-0">Indicadores no período. Atualizado ao aplicar filtros.</p>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="get" action="{{ route('relatorios.assistencia-dashboard-operacional') }}" class="row g-2 align-items-end">
                @include('partials.period-filter')
                @if (__countLocalAtivo() > 1)
                    <div class="col-md-4 col-12 mt-2 mt-md-0">
                        {!! Form::select('local_id', 'Local', __getLocaisAtivoUsuarioParaSelect())->attrs(['class' => 'form-select']) !!}
                    </div>
                @endif
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="ri-refresh-line"></i> Atualizar</button>
                </div>
            </form>
        </div>
    </div>

    @if (!$tplAssistencia)
        <div class="alert alert-warning">Este painel requer empresa com tipo de OS <strong>Assistência técnica</strong> na configuração geral.</div>
    @else
        <div class="row g-3">
            <div class="col-md-4">
                <div class="card border-primary h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Ordens no período</h6>
                        <div class="fs-3 fw-semibold">{{ $totalOs }}</div>
                    </div>
                </div>
            </div>
            @if($integraEstoque)
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Baixas manuais (perdas)</h6>
                        <div class="fs-5">{{ $qtdPerdas }} registro(s)</div>
                        <div class="small text-muted">Σ quantidades (abs.) {{ number_format($somaPerdasQtd, 3, ',', '.') }}</div>
                    </div>
                </div>
            </div>
            @endif
            <div class="col-md-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="text-muted">Atalhos</h6>
                        <a href="{{ url()->route('relatorios.index') }}#relatorios-assistencia-modulo">Ir para cartões de relatórios de assistência</a>
                    </div>
                </div>
            </div>
        </div>

        @php($maxEst = max(1, (int) $porEstado->max('total') ?: 1))
        <div class="card mt-3">
            <div class="card-header"><strong>Por estado (financeiro)</strong></div>
            <div class="card-body">
                @forelse($porEstado as $r)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $r->label }}</span><span>{{ $r->total }}</span></div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar" role="progressbar" style="width: {{ min(100, round(100 * $r->total / $maxEst)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Sem dados.</p>
                @endforelse
            </div>
        </div>

        @php($maxF = max(1, (int) $porFase->max('total') ?: 1))
        <div class="card mt-3">
            <div class="card-header"><strong>Por fase técnica</strong></div>
            <div class="card-body">
                @forelse($porFase as $r)
                    <div class="mb-2">
                        <div class="d-flex justify-content-between small"><span>{{ $r->label }}</span><span>{{ $r->total }}</span></div>
                        <div class="progress" style="height:8px;">
                            <div class="progress-bar bg-info" role="progressbar" style="width: {{ min(100, round(100 * $r->total / $maxF)) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-muted mb-0">Sem dados.</p>
                @endforelse
            </div>
        </div>

        <div class="card mt-3">
            <div class="card-header"><strong>Top técnicos (volume)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Técnico</th><th class="text-end">OS</th></tr></thead>
                    <tbody>
                        @forelse($porTecnico as $r)
                            <tr><td>{{ $r->nome }}</td><td class="text-end">{{ $r->qtd }}</td></tr>
                        @empty
                            <tr><td colspan="2" class="text-muted px-3 py-2">Sem dados.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($integraEstoque && $topPecas->isNotEmpty())
        <div class="card mt-3">
            <div class="card-header"><strong>Peças mais movimentadas (amostra)</strong></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Produto</th><th class="text-end">Qtd</th></tr></thead>
                    <tbody>
                        @foreach($topPecas as $p)
                            <tr><td>{{ $p->produto_nome }}</td><td class="text-end">{{ $p->qtd_total }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif
    @endif
</div>
@endsection
