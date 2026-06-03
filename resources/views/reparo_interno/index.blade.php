@extends('layouts.app', ['title' => 'Reparo interno'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
        <h4 class="mb-0">Reparo interno da loja</h4>
        <div>
            @can('reparo_interno_create')
            <a href="{{ route('reparo-interno.create') }}" class="btn btn-success btn-sm px-3">
                <i class="ri-add-line"></i> Novo reparo
            </a>
            @endcan
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 mb-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select" onchange="this.form.submit()">
                    <option value="">Todos</option>
                    @foreach($statusLabels as $k => $lbl)
                    <option value="{{ $k }}" @selected($status === $k)>{{ $lbl }}</option>
                    @endforeach
                </select>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead class="table-dark">
                    <tr>
                        <th>#</th>
                        <th>Status</th>
                        <th>Aparelho</th>
                        <th>Técnico</th>
                        <th>Abertura</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $r)
                    <tr>
                        <td>{{ $r->codigo_sequencial }}</td>
                        <td>
                            <span class="badge
                                @if($r->status === \App\Models\ReparoInterno::STATUS_FINALIZADO) bg-success
                                @elseif($r->status === \App\Models\ReparoInterno::STATUS_CANCELADO) bg-secondary
                                @elseif($r->status === \App\Models\ReparoInterno::STATUS_EM_ANDAMENTO) bg-info
                                @else bg-warning text-dark
                                @endif
                            ">{{ $statusLabels[$r->status] ?? $r->status }}</span>
                        </td>
                        <td>
                            @if($r->tradein_inventory_item_id)
                                Trade-in inv. #{{ $r->tradein_inventory_item_id }}
                                @if($r->tradeinInventoryItem && $r->tradeinInventoryItem->descricao_item)
                                    — {{ $r->tradeinInventoryItem->descricao_item }}
                                @endif
                            @elseif($r->produto)
                                {{ $r->produto->nome }}
                                @if($r->produtoUnico)
                                    <span class="text-muted">| {{ $r->produtoUnico->codigo }}</span>
                                @endif
                            @else
                                —
                            @endif
                        </td>
                        <td>{{ $r->funcionario ? $r->funcionario->nome : '—' }}</td>
                        <td>{{ __data_pt($r->created_at) }}</td>
                        <td>
                            @can('reparo_interno_view')
                            <a href="{{ route('reparo-interno.show', $r->id) }}" class="btn btn-sm btn-outline-primary">Abrir</a>
                            @endcan
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted">Nenhum reparo interno.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $data->links() }}
    </div>
</div>
@endsection
