<div class="card border mt-3 mb-3">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h5 class="mb-0">Assistência — controle técnico</h5>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('ordem-servico.fila-tecnica') }}" class="btn btn-outline-primary btn-sm">Fila técnica</a>
            <a href="{{ route('ordem-servico.painel-assistencia') }}" class="btn btn-outline-secondary btn-sm">Painel de status</a>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-4">
            <div class="col-lg-6">
                <h6 class="text-secondary border-bottom pb-2 mb-3">Timeline / histórico de andamento</h6>
                <div class="small" style="border-left: 2px solid #dee2e6; margin-left: 6px; padding-left: 14px;">
                    @forelse($assistenciaControleTimeline as $ev)
                        <div class="mb-3">
                            <div class="fw-semibold">{{ $ev['titulo'] ?? '' }}</div>
                            <div class="text-muted">{{ isset($ev['quando']) ? __data_pt($ev['quando'], true) : '' }}</div>
                            @if(!empty($ev['detalhe']))
                                <div class="mt-1">{{ $ev['detalhe'] }}</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-muted mb-0">Sem registros.</p>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-6">
                @canany(['ordem_servico_edit', 'ordem_servico_interna_edit'])
                    <h6 class="text-secondary border-bottom pb-2 mb-3">Fase, técnico e previsão</h6>
                    <form method="post" action="{{ route('ordem-servico.assistencia-controle', $ordem->id) }}" class="row g-2 mb-4">
                        @csrf
                        <div class="col-12">
                            <label class="form-label small">Nova observação (registra no histórico)</label>
                            <textarea name="observacao_andamento" class="form-control form-control-sm" rows="2" placeholder="Opcional — ex.: aguardando peça X..."></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Fase na bancada</label>
                            <select name="assistencia_fase_tecnica" class="form-select form-select-sm">
                                @foreach($assistenciaFasesTecnicasLista as $kf => $labelF)
                                    <option value="{{ $kf }}" @selected($ordem->assistencia_fase_tecnica === $kf)>{{ $labelF }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Técnico responsável</label>
                            <select name="tecnico_responsavel_id" class="form-select form-select-sm">
                                <option value="">— Sem técnico —</option>
                                @foreach($funcionarios as $f)
                                    <option value="{{ $f->id }}" @selected((int) ($ordem->tecnico_responsavel_id ?? 0) === (int) $f->id)>{{ $f->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">Previsão de entrega</label>
                            <input type="date" name="data_previsao_entrega" class="form-control form-control-sm"
                                value="{{ $ordem->data_previsao_entrega ? substr((string) $ordem->data_previsao_entrega, 0, 10) : '' }}">
                        </div>
                        <div class="col-12 mt-2">
                            <button type="submit" class="btn btn-success btn-sm px-4">Salvar controle técnico</button>
                        </div>
                    </form>

                    <h6 class="text-secondary border-bottom pb-2 mb-3">Checklist técnico</h6>
                    <p class="small text-muted mb-2">Marque cada etapa quando concluída; tudo é auditado no histórico.</p>
                    @foreach($ordem->assistenciaChecklistItens ?? [] as $chk)
                        <form method="post" action="{{ route('ordem-servico.assistencia-checklist', $ordem->id) }}" class="d-flex flex-wrap align-items-center gap-2 mb-2">
                            @csrf
                            <input type="hidden" name="item_codigo" value="{{ $chk->item_codigo }}">
                            <button type="submit" class="btn btn-sm {{ $chk->feito ? 'btn-outline-warning' : 'btn-outline-success' }}">
                                @if($chk->feito)
                                    Desmarcar
                                @else
                                    Marcar feito
                                @endif
                            </button>
                            <span>{{ $chk->titulo }}</span>
                            @if($chk->feito && $chk->feito_em)
                                <span class="text-muted small">{{ __data_pt($chk->feito_em, true) }}
                                    @if($chk->feitoPorUsuario)
                                        — {{ $chk->feitoPorUsuario->name }}
                                    @endif
                                </span>
                            @endif
                        </form>
                    @endforeach
                @else
                    <p class="text-muted small mb-0">Sem permissão para alterar fase/checklist nesta OS.</p>
                @endcanany
            </div>
        </div>
    </div>
</div>
