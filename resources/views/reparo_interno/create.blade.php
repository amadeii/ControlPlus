@extends('layouts.app', ['title' => 'Novo reparo interno'])

@section('content')
<div class="card mt-1">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h4 class="mb-0">Novo reparo interno</h4>
        <a href="{{ route('reparo-interno.index') }}" class="btn btn-danger btn-sm px-3"><i class="ri-arrow-left-double-fill"></i> Voltar</a>
    </div>
    <div class="card-body">
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="post" action="{{ route('reparo-interno.store') }}" id="form-reparo-interno-create">
            @csrf
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label d-block">Origem do aparelho</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="fonte" id="fonte-tradein" value="tradein" @checked(old('fonte', 'tradein') === 'tradein')>
                        <label class="form-check-label" for="fonte-tradein">Trade-in / inventário</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="fonte" id="fonte-estoque" value="estoque" @checked(old('fonte') === 'estoque')>
                        <label class="form-check-label" for="fonte-estoque">Estoque (catálogo)</label>
                    </div>
                </div>

                <div class="col-md-8 bloco-tradein">
                    <label class="form-label">Item inventário trade-in</label>
                    <select name="tradein_inventory_item_id" class="form-select">{{-- options include empty key --}}
                        @foreach($tradeinOpcoes as $tid => $tlabel)
                        <option value="{{ $tid }}" @selected(old('tradein_inventory_item_id') == $tid)>{{ $tlabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-8 bloco-estoque d-none">
                    <label class="form-label">Aparelho (produto no catálogo)</label>
                    <select id="create-reparo-produto-aparelho" class="form-select" style="width:100%"></select>
                    <input type="hidden" name="produto_id" id="create-reparo-produto-aparelho-id" value="{{ old('produto_id') }}">
                </div>

                <div class="col-md-8 bloco-serial d-none">
                    <label class="form-label">Serial em estoque</label>
                    <select name="produto_unico_id" id="create-reparo-produto-unico" class="form-select">
                        <option value="">— Selecione aparelho e carregue seriais —</option>
                        @if(old('produto_unico_id'))
                        <option value="{{ old('produto_unico_id') }}" selected>{{ old('produto_unico_id') }}</option>
                        @endif
                    </select>
                    <small class="text-muted">Obrigatório apenas para produtos serializados (tipo único).</small>
                </div>

                @if(count($opcoesLocal) > 0)
                <div class="col-md-6">
                    <label class="form-label">Local (baixa de peças)</label>
                    <select name="local_id" class="form-select">
                        <option value="">— Local ativo do usuário —</option>
                        @foreach($opcoesLocal as $lid => $ldesc)
                        <option value="{{ $lid }}" @selected(old('local_id') == $lid)>{{ $ldesc }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                <div class="col-md-6">
                    <label class="form-label">Depósito padrão (opcional)</label>
                    <select name="deposito_id" class="form-select">
                        @foreach($depositosPecaOpcoes as $did => $dlabel)
                        <option value="{{ $did }}" @selected(old('deposito_id') == $did)>{{ $dlabel }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Técnico responsável</label>
                    <select name="funcionario_id" class="form-select">
                        <option value="">—</option>
                        @foreach($funcionarios as $f)
                        <option value="{{ $f->id }}" @selected(old('funcionario_id') == $f->id)>{{ $f->nome }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-12">
                    <label class="form-label">Observações técnicas</label>
                    <textarea name="observacao_tecnica" class="form-control" rows="4">{{ old('observacao_tecnica') }}</textarea>
                </div>

                <div class="col-12 text-end">
                    <button type="submit" class="btn btn-success px-5">Abrir reparo</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

@section('js')
<script>
$(function () {
    function syncFonte() {
        var f = $('input[name=\"fonte\"]:checked').val();
        $('.bloco-tradein').toggleClass('d-none', f !== 'tradein');
        $('.bloco-estoque, .bloco-serial').addClass('d-none');
        if (f === 'estoque') {
            $('.bloco-estoque').removeClass('d-none');
            $('.bloco-serial').removeClass('d-none');
            refreshSerialAvailability();
        }
    }
    $('input[name=\"fonte\"]').on('change', syncFonte);
    syncFonte();

    $("#create-reparo-produto-aparelho").select2({
        minimumInputLength: 2,
        language: "pt-BR",
        placeholder: "Buscar produto aparelho...",
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

    $('#create-reparo-produto-aparelho').on('select2:select', function (e) {
        $('#create-reparo-produto-aparelho-id').val(e.params.data.id);
        refreshSerialAvailability();
    });

    $('#create-reparo-produto-aparelho').on('select2:clear', function () {
        $('#create-reparo-produto-aparelho-id').val('');
        $('#create-reparo-produto-unico').empty().append('<option value=\"\">—</option>');
    });

    function refreshSerialAvailability() {
        var pid = $('#create-reparo-produto-aparelho-id').val();
        if (!pid) {
            $('#create-reparo-produto-unico').empty().append('<option value=\"\">—</option>');
            return;
        }
        $.get(path_url + "reparo-interno/seriais-disponiveis", {
            empresa_id: $("#empresa_id").val(),
            produto_id: pid
        }).done(function (rows) {
            var $s = $('#create-reparo-produto-unico');
            $s.empty().append('<option value=\"\">— Selecione serial —</option>');
            $.each(rows, function (_, r) {
                $s.append($('<option>').attr('value', r.id).text(r.text));
            });
        }).fail(function () {
            $('#create-reparo-produto-unico').empty().append('<option value=\"\">Erro ao carregar seriais (produto não serializado ou sem permissão)</option>');
        });
    }

    $('#form-reparo-interno-create').on('submit', function () {
        if ($('input[name=\"fonte\"]:checked').val() === 'estoque') {
            $('select[name=\"tradein_inventory_item_id\"]').prop('disabled', true);
        } else {
            $('#create-reparo-produto-aparelho-id').prop('disabled', true);
            $('#create-reparo-produto-unico').prop('disabled', true);
        }
    });
});
</script>
@endsection
