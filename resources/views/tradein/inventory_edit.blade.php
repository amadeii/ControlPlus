@extends('layouts.app', ['title' => 'Editar Item de Inventário'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Editar Item de Inventário Trade-in #{{ $item->id }}</h4>
        <a href="{{ route('tradein.inventory.index', ['empresa_id' => request()->empresa_id]) }}" class="btn btn-danger btn-sm px-3">
            <i class="ri-arrow-left-double-fill"></i> Voltar
        </a>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('tradein.inventory.update', ['id' => $item->id, 'empresa_id' => request()->empresa_id]) }}">
            @csrf
            @method('PUT')

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Produto do catálogo</label>
                    <select class="form-select" name="produto_id" id="inv-produto-select" style="width:100%">
                        @if($produto)
                            <option value="{{ $produto->id }}" selected>{{ $produto->nome }}</option>
                        @endif
                    </select>
                    <small class="text-muted">Busque o produto no catálogo (opcional).</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Serial / IMEI</label>
                    <input type="text" class="form-control" name="serial" value="{{ old('serial', $item->serial) }}" placeholder="Serial ou IMEI">
                </div>

                <div class="col-md-8">
                    <label class="form-label">Descrição do item</label>
                    <input type="text" class="form-control" name="descricao_item" value="{{ old('descricao_item', $item->descricao_item) }}" placeholder="Descrição">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="status">
                        <option value="{{ \App\Models\TradeinInventoryItem::STATUS_PENDING_TRANSFER }}" @selected(old('status', $item->status) === \App\Models\TradeinInventoryItem::STATUS_PENDING_TRANSFER)>Aguardando transferência</option>
                        <option value="{{ \App\Models\TradeinInventoryItem::STATUS_EM_ASSISTENCIA }}" @selected(old('status', $item->status) === \App\Models\TradeinInventoryItem::STATUS_EM_ASSISTENCIA)>Em assistência</option>
                        <option value="{{ \App\Models\TradeinInventoryItem::STATUS_TRANSFERRED }}" @selected(old('status', $item->status) === \App\Models\TradeinInventoryItem::STATUS_TRANSFERRED)>Transferido</option>
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Observação técnica</label>
                    <textarea class="form-control" name="observacao_tecnica" rows="3">{{ old('observacao_tecnica', $item->observacao_tecnica) }}</textarea>
                </div>
            </div>

            @if(isset($historicoPendenciasAssistenciaOs) && $historicoPendenciasAssistenciaOs->isNotEmpty())
            @php
                $qtdPendentesAssistencia = $historicoPendenciasAssistenciaOs->where('status', \App\Models\AssistenciaOsPecaBaixa::STATUS_PENDENTE)->count();
                $qtdBaixadasAssistencia = $historicoPendenciasAssistenciaOs->where('status', \App\Models\AssistenciaOsPecaBaixa::STATUS_BAIXADO)->count();
                $qtdCanceladasAssistencia = $historicoPendenciasAssistenciaOs->where('status', \App\Models\AssistenciaOsPecaBaixa::STATUS_CANCELADO)->count();
            @endphp
            <div class="alert alert-light border mt-4">
                <strong>Rastreabilidade da assistência:</strong>
                relatórios de consumo continuam baseados apenas em baixa real (`os_consumo_peca`) e o custo agregado aparece somente após o commit administrativo.
                <div class="mt-2">
                    <span class="badge bg-warning text-dark">Pendentes: {{ $qtdPendentesAssistencia }}</span>
                    <span class="badge bg-success ms-1">Baixadas: {{ $qtdBaixadasAssistencia }}</span>
                    <span class="badge bg-secondary ms-1">Canceladas: {{ $qtdCanceladasAssistencia }}</span>
                </div>
            </div>

            <div class="table-responsive mt-3">
                <h5 class="mb-2">Histórico operacional — pendências e baixas</h5>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>OS</th>
                            <th>Peça</th>
                            <th>Status</th>
                            <th>Resumo</th>
                            <th>Baixa/aprovação</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historicoPendenciasAssistenciaOs as $pendencia)
                        <tr>
                            <td>
                                @if($pendencia->ordemServico)
                                <a href="{{ route('ordem-servico.show', $pendencia->ordem_servico_id) }}">#{{ $pendencia->ordemServico->codigo_sequencial }}</a>
                                @else
                                {{ $pendencia->ordem_servico_id }}
                                @endif
                            </td>
                            <td>{{ $pendencia->produtoOs ? $pendencia->produtoOs->descricaoLinha() : '—' }}</td>
                            <td><span class="badge {{ $pendencia->statusBadgeClass() }}">{{ $pendencia->statusLabel() }}</span></td>
                            <td>{{ $pendencia->statusResumoOperacional() }}</td>
                            <td>
                                @if($pendencia->baixado_em)
                                {{ __data_pt($pendencia->baixado_em, 0) }}
                                @if($pendencia->aprovadoPor)
                                <small class="d-block text-muted">{{ $pendencia->aprovadoPor->name }}</small>
                                @endif
                                @else
                                —
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            @if(isset($historicoCustoAssistenciaOs) && $historicoCustoAssistenciaOs->isNotEmpty())
            <div class="table-responsive mt-4">
                <h5 class="mb-2">Histórico — custo via peças (OS assistência)</h5>
                <table class="table table-bordered table-sm">
                    <thead class="table-light">
                        <tr>
                            <th>OS</th>
                            <th>Peça</th>
                            <th>Qtd</th>
                            <th>Incr.</th>
                            <th>Custo antes</th>
                            <th>Custo depois</th>
                            <th>Usuário</th>
                            <th>Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($historicoCustoAssistenciaOs as $h)
                        <tr>
                            <td>@if($h->ordemServico)<a href="{{ route('ordem-servico.show', $h->ordem_servico_id) }}">#{{ $h->ordemServico->codigo_sequencial }}</a>@else {{ $h->ordem_servico_id }} @endif</td>
                            <td>{{ $h->peca ? $h->peca->nome : '—' }}</td>
                            <td>{{ $h->quantidade_peca }}</td>
                            <td>{{ __moeda($h->valor_custo_incremento) }}</td>
                            <td>{{ __moeda($h->custo_aparelho_antes) }}</td>
                            <td>{{ __moeda($h->custo_aparelho_depois) }}</td>
                            <td>{{ $h->user ? $h->user->name : '—' }}</td>
                            <td>{{ __data_pt($h->created_at ?? '') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif

            <div class="mt-4 text-end">
                <a href="{{ route('tradein.inventory.index', ['empresa_id' => request()->empresa_id]) }}" class="btn btn-light me-2">Cancelar</a>
                <button type="submit" class="btn btn-success px-5">Salvar alterações</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
$(function() {
    $("#inv-produto-select").select2({
        minimumInputLength: 2,
        language: "pt-BR",
        placeholder: "Buscar produto...",
        allowClear: true,
        width: "100%",
        ajax: {
            cache: false,
            url: path_url + "api/produtos",
            dataType: "json",
            data: function (params) {
                return {
                    pesquisa: params.term,
                    empresa_id: $("#empresa_id").val(),
                    usuario_id: $("#usuario_id").val(),
                };
            },
            processResults: function (response) {
                var results = [];
                $.each(response, function (i, v) {
                    results.push({ id: v.id, text: v.nome || v.text || String(v.id) });
                });
                return { results: results };
            },
        },
    });
});
</script>
@endsection
