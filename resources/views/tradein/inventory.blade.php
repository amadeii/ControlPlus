@extends('layouts.app', ['title' => 'Estoque Trade-in'])

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="card-title mb-0">Estoque Trade-in</h4>
                </div>
                <div class="card-body border-top">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Trade-in</th>
                                    <th>Cliente</th>
                                    <th>Descrição</th>
                                    <th>Serial</th>
                                    <th>Valor</th>
                                    <th>Status</th>
                                    <th>Criado em</th>
                                    <th class="text-end" style="min-width: 15rem" aria-label="Ações"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($items as $item)
                                    @php($ordemInterna = $ordensServicoPorTradein[$item->id] ?? null)
                                    <tr>
                                        <td>{{ $item->id }}</td>
                                        <td>{{ $item->tradein_id }}</td>
                                        <td>{{ $clientes[$item->cliente_id] ?? '—' }}</td>
                                        <td>{{ $item->descricao_item }}</td>
                                        <td>{{ $item->serial ?: '—' }}</td>
                                        <td>R$ {{ __moeda($item->tradein->valor_avaliado ?? $item->valor ?? 0) }}</td>
                                        <td>{{ $item->status }}</td>
                                        <td>{{ $item->created_at ? $item->created_at->format('d/m/Y H:i') : '—' }}</td>
                                        <td class="text-end" style="min-width: 15rem">
                                            <div
                                                class="d-inline-flex flex-nowrap justify-content-end gap-2 align-items-center flex-shrink-0">
                                                <a href="{{ route('tradein.inventory.edit', ['id' => $item->id, 'empresa_id' => request()->empresa_id]) }}"
                                                    class="btn btn-sm btn-outline-secondary" title="Editar">
                                                    <i class="ri-edit-line"></i>
                                                </a>
                                                @if ($item->status === \App\Models\TradeinInventoryItem::STATUS_PENDING_TRANSFER)
                                                    <form method="POST"
                                                        action="{{ route('tradein.inventory.assistencia', ['id' => $item->id, 'empresa_id' => request()->empresa_id]) }}"
                                                        class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-outline-warning">
                                                            Enviar para assistência
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('tradein.inventory.transfer', ['id' => $item->id, 'empresa_id' => request()->empresa_id]) }}"
                                                        class="btn btn-sm btn-outline-primary c">
                                                        Transferir para estoque real
                                                    </a>
                                                @elseif ($item->status === \App\Models\TradeinInventoryItem::STATUS_EM_ASSISTENCIA)
                                                    @if ($ordemInterna)
                                                        <a href="{{ route('ordem-servico.show', $ordemInterna->id) }}"
                                                            class="btn btn-sm btn-outline-info">
                                                            OS #{{ $ordemInterna->codigo_sequencial }} ({{ strtoupper($ordemInterna->estado) }})
                                                        </a>
                                                        @if ($ordemInterna->estado === 'ap')
                                                            <form method="POST"
                                                                action="{{ route('tradein.inventory.aprovar-venda', ['id' => $item->id, 'empresa_id' => request()->empresa_id]) }}"
                                                                class="d-inline">
                                                                @csrf
                                                                <button type="submit" class="btn btn-sm btn-outline-success">
                                                                    Aprovar pós-reparo
                                                                </button>
                                                            </form>
                                                        @else
                                                            <span class="badge bg-warning text-dark">Aguardando OS aprovada</span>
                                                        @endif
                                                    @else
                                                        <span class="badge bg-secondary text-white">Sem OS interna vinculada</span>
                                                    @endif
                                                @else
                                                    <span class="badge bg-success text-white">Transferido</span>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">Nenhum item encontrado.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="mt-3">
                        {{ $items->appends(['status' => $status])->links() }}
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
