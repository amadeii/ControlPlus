@extends('layouts.app', ['title' => 'Reparo interno #' . $reparo->codigo_sequencial])

@section('content')
@php $statusLab = \App\Models\ReparoInterno::statuses(); @endphp
<div class="card mt-1">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h4 class="mb-0">Reparo interno #{{ $reparo->codigo_sequencial }}</h4>
            <span class="badge
                @if($reparo->status === \App\Models\ReparoInterno::STATUS_FINALIZADO) bg-success
                @elseif($reparo->status === \App\Models\ReparoInterno::STATUS_CANCELADO) bg-secondary
                @elseif($reparo->status === \App\Models\ReparoInterno::STATUS_EM_ANDAMENTO) bg-info
                @else bg-warning text-dark
                @endif">{{ $statusLab[$reparo->status] ?? $reparo->status }}</span>
        </div>
        <a href="{{ route('reparo-interno.index') }}" class="btn btn-danger btn-sm px-3"><i class="ri-arrow-left-double-fill"></i> Lista</a>
    </div>
    <div class="card-body">
        @if(session('flash_success'))<div class="alert alert-success">{{ session('flash_success') }}</div>@endif
        @if(session('flash_error'))<div class="alert alert-danger">{{ session('flash_error') }}</div>@endif
        @if(session('flash_warning'))<div class="alert alert-warning">{{ session('flash_warning') }}</div>@endif

        <div class="row mb-3">
            <div class="col-md-12">
                <h5 class="border-bottom pb-2">Aparelho</h5>
                @if($reparo->tradein_inventory_item_id && $reparo->tradeinInventoryItem)
                    <p class="mb-1"><strong>Trade-in inventário #{{ $reparo->tradeinInventoryItem->id }}</strong>
                        — {{ $reparo->tradeinInventoryItem->descricao_item }}
                        @if($reparo->tradeinInventoryItem->serial) | Serial {{ $reparo->tradeinInventoryItem->serial }} @endif
                    </p>
                    <p class="small text-muted mb-0">Custo atual (inventário): <strong>R$ {{ __moeda($reparo->tradeinInventoryItem->valor ?? 0) }}</strong>
                        @if($reparo->tradeinInventoryItem->tradein)<span class="ms-2">Avaliação trade-in: R$ {{ __moeda($reparo->tradeinInventoryItem->tradein->valor_avaliado ?? 0) }}</span>@endif
                    </p>
                @elseif($reparo->produto)
                    <p class="mb-1"><strong>{{ $reparo->produto->nome }}</strong>
                        @if($reparo->produtoUnico) <span class="text-muted">| Serial {{ $reparo->produtoUnico->codigo }}</span> @endif
                    </p>
                    <p class="small mb-0">Custo atual (valor de compra no cadastro): <strong>R$ {{ __moeda($reparo->produto->valor_compra ?? 0) }}</strong></p>
                @endif
            </div>
        </div>

        @if($reparo->permiteEditarConteudo())
            @can('reparo_interno_edit')
            <div class="d-flex flex-wrap gap-2 mb-3">
                @if($reparo->status === \App\Models\ReparoInterno::STATUS_ABERTO)
                <form method="post" action="{{ route('reparo-interno.em-andamento', $reparo->id) }}" class="d-inline">@csrf<button class="btn btn-info btn-sm" type="submit">Marcar em andamento</button></form>
                @endif
                <form method="post" action="{{ route('reparo-interno.finalizar', $reparo->id) }}" class="d-inline" onsubmit="return confirm('Finalizar este reparo? Não será possível mais alterar peças ou observações.');">@csrf<button class="btn btn-success btn-sm" type="submit">Finalizar reparo</button></form>
                <form method="post" action="{{ route('reparo-interno.cancelar', $reparo->id) }}" class="d-inline" onsubmit="return confirm('Cancelar reparo? Será aplicado estorno automático das peças e reversão dos custos do aparelho.');">@csrf<button class="btn btn-outline-danger btn-sm" type="submit">Cancelar reparo</button></form>
            </div>
            @endcan

            @can('reparo_interno_edit')
            <div class="card border mb-4">
                <div class="card-body">
                    <h5>Técnico e observações</h5>
                    <form method="post" action="{{ route('reparo-interno.update', $reparo->id) }}" class="row g-2">
                        @csrf
                        @method('put')
                        <div class="col-md-4">
                            <label class="form-label">Técnico responsável</label>
                            <select name="funcionario_id" class="form-select">
                                <option value="">—</option>
                                @foreach(\App\Models\Funcionario::where('empresa_id', request()->empresa_id)->orderBy('nome')->get() as $f)
                                <option value="{{ $f->id }}" @selected($reparo->funcionario_id == $f->id)>{{ $f->nome }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Observações técnicas</label>
                            <textarea name="observacao_tecnica" class="form-control" rows="3">{{ old('observacao_tecnica', $reparo->observacao_tecnica) }}</textarea>
                        </div>
                        <div class="col-12 text-end"><button type="submit" class="btn btn-primary btn-sm px-4">Salvar</button></div>
                    </form>
                </div>
            </div>
            @endcan
        @else
            <div class="alert alert-light border mb-3">
                <small>Aberto por {{ $reparo->usuario ? $reparo->usuario->name : '—' }} em {{ __data_pt($reparo->created_at) }}.</small><br>
                @if($reparo->finalizado_at)<small>Finalizado em {{ __data_pt($reparo->finalizado_at) }}.</small>@endif
                @if($reparo->cancelado_at)<small>Cancelado em {{ __data_pt($reparo->cancelado_at) }}.</small>@endif
            </div>
            @if($reparo->observacao_tecnica)
            <div class="mb-3"><strong>Observações:</strong><br>{{ nl2br(e($reparo->observacao_tecnica)) }}</div>
            @endif
        @endif

        @can('reparo_interno_edit')
        @if($reparo->permiteEditarConteudo())
        <div class="card border mb-4">
            <div class="card-body">
                <h5>Incluir peça (consome estoque quando o produto tem controle)</h5>
                <form method="post" action="{{ route('reparo-interno.linha.store', $reparo->id) }}" class="row g-2">
                    @csrf
                    <input type="hidden" name="reparo_interno_id" value="{{ $reparo->id }}">
                    <div class="col-md-4">
                        <label class="form-label">Peça</label>
                        <select name="produto_id" id="reparo-peca-produto-select" style="width:100%" class="form-select" required></select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Qtd</label>
                        <input type="tel" name="quantidade_produto" class="form-control moeda" value="{{ old('quantidade_produto', '1,0000') }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small">Valor (opcional)</label>
                        <input type="tel" name="valor_produto" class="form-control moeda" value="{{ old('valor_produto', '0,0000') }}">
                    </div>
                    @if(!empty($mostrarSelectDeposito) && $mostrarSelectDeposito)
                    <div class="col-md-4">
                        <label class="form-label">Depósito baixa</label>
                        <select name="deposito_reparo_peca_id" class="form-select">
                            @foreach($depositosPecaOpcoes ?? [] as $dk => $dv)
                            <option value="{{ $dk }}">{{ $dv }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-sm"><i class="ri-add-line"></i> Adicionar peça</button>
                    </div>
                </form>
            </div>
        </div>
        @endif
        @endcan

        <h5 class="border-bottom pb-2">Peças consumidas</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-striped">
                <thead class="table-light"><tr><th>Produto</th><th>Qtd</th><th>Ações</th></tr></thead>
                <tbody>
                    @forelse($reparo->linhas as $ln)
                    <tr>
                        <td>{{ $ln->produto ? $ln->produto->nome : $ln->produto_id }}</td>
                        <td>{{ $ln->quantidade }}</td>
                        <td>
                            @can('reparo_interno_edit')
                            @if($reparo->permiteEditarConteudo())
                            <form method="post" action="{{ route('reparo-interno.linha.destroy', $ln->id) }}" class="d-inline" onsubmit="return confirm('Remover peça e estornar estoque/custo?');">
                                @csrf @method('delete')
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="ri-delete-bin-line"></i></button>
                            </form>
                            @endif
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted">Nenhuma peça.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($reparo->custoPecaLancamentos->isNotEmpty())
        <h5 class="border-bottom pb-2">Impacto no custo do aparelho</h5>
        <div class="table-responsive mb-4">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Peça</th><th>Incr.</th><th>Antes</th><th>Depois</th><th>Usuário</th><th>Data</th></tr>
                </thead>
                <tbody>
                    @foreach($reparo->custoPecaLancamentos as $c)
                    <tr>
                        <td>{{ $c->peca ? $c->peca->nome : '—' }}</td>
                        <td>{{ __moeda($c->valor_custo_incremento) }}</td>
                        <td>{{ __moeda($c->custo_aparelho_antes) }}</td>
                        <td>{{ __moeda($c->custo_aparelho_depois) }}</td>
                        <td>{{ $c->user ? $c->user->name : '—' }}</td>
                        <td>{{ __data_pt($c->created_at) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <h5 class="border-bottom pb-2">Histórico</h5>
        <div class="table-responsive">
            <table class="table table-sm">
                @foreach($reparo->eventos as $ev)
                <tr>
                    <td class="text-muted" style="width:180px">{{ __data_pt($ev->created_at) }}</td>
                    <td><span class="badge bg-light text-dark border">{{ $ev->tipo }}</span></td>
                    <td>{{ $ev->user ? $ev->user->name : '—' }}</td>
                    <td>{{ $ev->mensagem }}</td>
                </tr>
                @endforeach
            </table>
        </div>
    </div>
</div>
@endsection

@section('js')
@can('reparo_interno_edit')
@if($reparo->permiteEditarConteudo())
<script>
$(function () {
    $("#reparo-peca-produto-select").select2({
        minimumInputLength: 2,
        language: "pt-BR",
        placeholder: "Buscar peça...",
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
@endif
@endcan
@endsection
