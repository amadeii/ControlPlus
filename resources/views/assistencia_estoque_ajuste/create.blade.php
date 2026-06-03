@extends('layouts.app', ['title' => 'Nova baixa manual — assistência'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Nova baixa manual (perda / quebra / defeito / descarte)</h4>
        <a href="{{ route('assistencia-estoque-ajuste.index') }}" class="btn btn-danger btn-sm px-3"><i class="ri-arrow-left-double-fill"></i> Voltar</a>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('assistencia-estoque-ajuste.store') }}">
            @csrf
            <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey ?? '') }}">
            <div class="row g-3">
                <div class="col-md-8">
                    {!! Form::select('produto_id', 'Peça (produto)', [])->attrs(['class' => 'form-select produtos_filtro'])->id('ajuste_manual_produto_id') !!}
                </div>
                <div class="col-md-4">
                    <label class="form-label">Quantidade</label>
                    <input type="text" name="quantidade" class="form-control" value="{{ old('quantidade') }}" required inputmode="decimal">
                </div>
                @if(count($opcoesLocal) > 0)
                <div class="col-md-6">
                    <label class="form-label">Local (quando sem depósito específico)</label>
                    <select name="local_id" class="form-select">
                        <option value="">— Local ativo do usuário —</option>
                        @foreach($opcoesLocal as $lid => $ldesc)
                        <option value="{{ $lid }}" @selected(old('local_id') == $lid)>{{ $ldesc }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label">Depósito (opcional)</label>
                    <select name="deposito_id" class="form-select">
                        @foreach($depositosPecaOpcoes as $did => $dlabel)
                        <option value="{{ $did }}" @selected(old('deposito_id') == $did)>{{ $dlabel }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Motivo</label>
                    <select name="motivo" class="form-select" required>
                        <option value="">— Selecione —</option>
                        @foreach($motivosOpcoes as $k => $label)
                        <option value="{{ $k }}" @selected(old('motivo') === $k)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">Observação <span class="text-danger">*</span></label>
                    <textarea name="observacao" class="form-control" rows="4" required minlength="3" maxlength="5000" placeholder="Descreva o que ocorreu (obrigatório).">{{ old('observacao') }}</textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success px-5">Registrar baixa</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
