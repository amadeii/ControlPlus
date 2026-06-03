@extends('layouts.app', ['title' => 'Baixa manual #' . $item->id])

@section('content')
@php
    $motivos = \App\Models\AssistenciaEstoqueAjusteManual::motivosLabels();
@endphp
<div class="card mt-1">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Baixa manual #{{ $item->id }}</h4>
        <a href="{{ route('assistencia-estoque-ajuste.index') }}" class="btn btn-outline-secondary btn-sm">Voltar à lista</a>
    </div>
    <div class="card-body">
        <dl class="row mb-0">
            <dt class="col-sm-3">Data</dt>
            <dd class="col-sm-9">{{ __data_pt($item->created_at, true) }}</dd>
            <dt class="col-sm-3">Peça</dt>
            <dd class="col-sm-9">{{ optional($item->produto)->nome ?? '—' }}</dd>
            <dt class="col-sm-3">Quantidade</dt>
            <dd class="col-sm-9">{{ $item->quantidade }}</dd>
            <dt class="col-sm-3">Motivo</dt>
            <dd class="col-sm-9">{{ $motivos[$item->motivo] ?? $item->motivo }}</dd>
            <dt class="col-sm-3">Depósito</dt>
            <dd class="col-sm-9">{{ optional($item->deposito)->nome ?? '—' }}</dd>
            <dt class="col-sm-3">Usuário</dt>
            <dd class="col-sm-9">{{ optional($item->user)->name ?? '—' }}</dd>
            <dt class="col-sm-3">Observação</dt>
            <dd class="col-sm-9"><pre class="mb-0" style="white-space: pre-wrap;">{{ $item->observacao }}</pre></dd>
        </dl>
    </div>
</div>
@endsection
