@extends('layouts.app', ['title' => 'Assistência — baixa manual'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h4 class="mb-0">Baixa manual (perdas / ajustes)</h4>
        <div class="d-flex gap-2">
            @can('assistencia_estoque_ajuste_create')
                <a href="{{ route('assistencia-estoque-ajuste.create') }}" class="btn btn-success btn-sm px-3">Nova baixa</a>
            @endcan
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Peça</th>
                        <th>Qtd</th>
                        <th>Motivo</th>
                        <th>Depósito</th>
                        <th>Usuário</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($data as $row)
                        @php
                            $motivos = \App\Models\AssistenciaEstoqueAjusteManual::motivosLabels();
                        @endphp
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ __data_pt($row->created_at, true) }}</td>
                            <td>{{ optional($row->produto)->nome ?? '—' }}</td>
                            <td>{{ $row->quantidade }}</td>
                            <td>{{ $motivos[$row->motivo] ?? $row->motivo }}</td>
                            <td>{{ optional($row->deposito)->nome ?? '—' }}</td>
                            <td>{{ optional($row->user)->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('assistencia-estoque-ajuste.show', $row->id) }}" class="btn btn-outline-primary btn-sm">Detalhes</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nenhum registro.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($data->hasPages())
        <div class="card-footer">{{ $data->links() }}</div>
    @endif
</div>
@endsection
