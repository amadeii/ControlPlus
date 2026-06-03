<div class="row g-2">
    @if($configGeral->tipo_ordem_servico == 'assistencia técinica')
    <input type="hidden" name="escopo_ordem_servico"
        value="{{ old('escopo_ordem_servico', isset($item) ? ($item->escopo_ordem_servico ?? \App\Models\OrdemServico::ESCOPO_CLIENTE) : request('escopo_ordem_servico', \App\Models\OrdemServico::ESCOPO_CLIENTE)) }}">
    @endif

    @if(__countLocalAtivo() > 1)
    <div class="col-md-2">
        <label for="">Local</label>

        <select id="inp-local_id" required class="select2 class-required" data-toggle="select2" name="local_id">
            @foreach(__getLocaisAtivoUsuario() as $local)
            <option @isset($item) @if($item->local_id == $local->id) selected @endif @endif value="{{ $local->id }}">{{ $local->descricao }}</option>
            @endforeach
        </select>
    </div>
    @else
    <input id="inp-local_id" type="hidden" value="{{ __getLocalAtivo() ? __getLocalAtivo()->id : '' }}" name="local_id">
    @endif
    
    <div class="col-md-4">
        {!!Form::select('cliente_id', 'Cliente')->attrs(['class' => 'select2'])->options(isset($item) ? [$item->cliente_id => $item->cliente->razao_social] : [])
        ->required()
        !!}
    </div>
    <div class="col-md-2">
        <label class="">Início</label>
        <input required type="text" name="data_inicio" id="datetime-datepicker" class="form-control" value="{{ isset($item) ? $item->data_inicio : '' }}">
        @if($errors->has('data_inicio'))
        <label class="text-danger">Campo Obrigatório</label>
        @endif
    </div>
    
    <div class="col-md-3">
        {!!Form::select(
            'funcionario_id',
            $configGeral->tipo_ordem_servico == 'assistencia técinica' ? 'Atendente (recepção)' : 'Funcionário',
            ['' => 'Selecione'] + $funcionario->pluck('nome', 'id')->all())->attrs(['class' => 'form-select'])->required(__isSegmentoPlanoOtica())
        !!}
    </div>

    @if(!__isSegmentoPlanoOtica() && $configGeral->tipo_ordem_servico == 'oficina')
    <div class="col-md-3">
        <label>Veículo</label>
        <div class="input-group flex-nowrap">
            <select name="veiculo_id" id="veiculo_id" class="form-select select2">
                <option value="">Selecione</option>
                @foreach($veiculos as $v)
                <option value="{{ $v->id }}">{{ $v->info }}</option>
                @endforeach
            </select>
            @can('veiculos_create')
            <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#modal_novo_veiculo" type="button">
                <i class="ri-add-circle-fill"></i>
            </button>
            @endcan
        </div>
    </div>
    @endif

    @if($configGeral->tipo_ordem_servico == 'assistencia técinica')
    <div class="col-md-3">
        {!!Form::select('tecnico_responsavel_id', 'Técnico responsável', ['' => 'Selecione'] + $funcionario->pluck('nome', 'id')->all())
        ->attrs(['class' => 'form-select']) !!}
    </div>
    <div class="col-md-2">
        {!!Form::date('data_previsao_entrega', 'Previsão de entrega') !!}
    </div>
    <div class="col-md-3">
        {!!Form::select('assistencia_fase_tecnica', 'Fase na bancada', ['' => 'Na fila (padrão)'] + \App\Models\OrdemServico::assistenciaFasesTecnicas())
        ->attrs(['class' => 'form-select']) !!}
    </div>
    <hr>
    <div class="col-md-2">
        {!!Form::select('tipo_servico', 'Tipo do serviço', \App\Models\OrdemServico::tiposDeServico())
        ->attrs(['class' => 'form-select'])->required()
        !!}
    </div>

    <div class="col-md-3">
        {!!Form::text('equipamento', 'Equipamento / modelo comercial')
        ->value(isset($item) ? ($item->equipamento ?? '') : '')
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::text('marca_equipamento', 'Marca (digitada)')
        ->value(isset($item) ? ($item->marca_equipamento ?? '') : '')
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::text('modelo_equipamento', 'Modelo (digitado)')
        ->value(isset($item) ? ($item->modelo_equipamento ?? '') : '')
        !!}
    </div>

    <div class="col-md-2">
        {!!Form::text('numero_serie', 'IMEI / Número de série')
        ->value(isset($item) ? ($item->numero_serie ?? '') : '')
        !!}
    </div>
    @if(!isset($item) || !empty($duplicandoOs))
    <div class="col-md-2">
        <label>Senha do aparelho</label>
        <input type="password" name="senha_aparelho" class="form-control" autocomplete="off">
        <small class="text-muted">Aparece apenas no termo de entrada.</small>
    </div>
    @endif
    <div class="col-md-2">
        {!!Form::text('cor', 'Cor')
        !!}
    </div>
    <div class="col-md-4">
        {!!Form::textarea('acessorios', 'Acessórios recebidos')
        ->attrs(['rows' => '2', 'placeholder' => 'Ex.: carregador, capa, chip, nenhum...'])
        ->value(isset($item) ? ($item->acessorios ?? '') : '')
        !!}
    </div>

    @can('tradein_view')
    <div class="col-md-5">
        <label>Aparelho trade-in (inventário)</label>
        <select name="tradein_inventory_item_id" class="form-select select2" data-toggle="select2">
            @foreach(($tradeinOpcoesInventarioOs ?? ['' => '— Não vinculado —']) as $tid => $tlabel)
            <option value="{{ $tid }}" @selected((string) old('tradein_inventory_item_id', isset($item) ? ($item->tradein_inventory_item_id ?? '') : request('tradein_inventory_item_id', '')) === (string) $tid)>{{ $tlabel }}</option>
            @endforeach
        </select>
        <small class="text-muted">Peças da OS que geram baixa no estoque somam o valor de compra do produto ao custo do aparelho no inventário trade-in.</small>
    </div>
    @endcan

    <div class="col-md-12">
        {!!Form::textarea('diagnostico_cliente', 'Diagnóstico do cliente')
        ->attrs(['rows' => '4', 'class' => 'tiny'])
        !!}
    </div>

    @if(!isset($item) || !empty($duplicandoOs))
    <div class="col-md-12">
        <h5 class="mt-2 mb-2">Checklist físico de entrada</h5>
        <div class="row g-2">
            @foreach(\App\Models\OrdemServico::assistenciaChecklistFisicoDefinicoes() as $codigoChecklistFisico => $tituloChecklistFisico)
            <div class="col-md-4">
                <label>{{ $tituloChecklistFisico }}</label>
                <select name="checklist_fisico[{{ $codigoChecklistFisico }}]" class="form-select" required>
                    <option value="">Selecione</option>
                    @foreach(\App\Models\OrdemServico::assistenciaChecklistFisicoEstados() as $estadoChecklistFisico => $labelChecklistFisico)
                    <option value="{{ $estadoChecklistFisico }}">{{ $labelChecklistFisico }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-8">
                <label>Observação - {{ $tituloChecklistFisico }}</label>
                <input type="text" name="checklist_fisico_observacao[{{ $codigoChecklistFisico }}]" class="form-control" maxlength="1000">
            </div>
            @endforeach
        </div>
    </div>

    <div class="col-md-12">
        <h5 class="mt-2 mb-2">Fotos do aparelho <small class="text-muted">(opcional)</small></h5>
        <div class="row g-2">
            <div class="col-md-3">
                <label>Frente</label>
                <input type="file" name="fotos[frente]" class="form-control" accept="image/*">
            </div>
            <div class="col-md-3">
                <label>Verso</label>
                <input type="file" name="fotos[verso]" class="form-control" accept="image/*">
            </div>
            <div class="col-md-3">
                <label>Laterais</label>
                <input type="file" name="fotos[laterais][]" class="form-control" accept="image/*" multiple>
            </div>
            <div class="col-md-3">
                <label>Outras</label>
                <input type="file" name="fotos[outras][]" class="form-control" accept="image/*" multiple>
            </div>
        </div>
    </div>
    @endif

    @endif

    <div class="col-md-12">
        {!!Form::textarea('descricao', 'Descrição/Observação')
        ->attrs(['rows' => '6', 'class' => 'tiny'])
        !!}
    </div>

    @if(__isSegmentoPlanoOtica())
    @include('ordem_servico.partials.otica_forms')
    @endif


    <hr class="mt-4">
    <div class="col-12" style="text-align: right;">
        <button type="submit" class="btn btn-success px-5" id="btn-store">Salvar</button>
    </div>
</div>

@section('js')
<script src="/tinymce/tinymce.min.js"></script>
<script type="text/javascript">
    $(function(){
        tinymce.init({ selector: 'textarea.tiny', language: 'pt_BR'})

        setTimeout(() => {
            $('.tox-promotion, .tox-statusbar__right-container').addClass('d-none')
        }, 500)
    })

    $(document).on("click", ".btn-store-veiculo", function () {
        var json = {};
        var a = $("#modal_novo_veiculo").serializeArray();
        let msg = ""
        $("#modal_novo_veiculo").find('input, select').each(function () {
            if (($(this).val() == "" || $(this).val() == null) && $(this).attr("type") != "hidden" && $(this).attr("type") != "file" && !$(this).hasClass("ignore")) {
                if($(this).prev()[0].textContent){
                    msg += "Informe o campo " + $(this).prev()[0].textContent + "\n"
                }
            }
            if($(this)[0].name){
                let name = $(this)[0].name
                name = name.replace("novo_", "")
                json[name] = $(this).val()
            }
        })

        json['empresa_id'] = $('#empresa_id').val()
        // console.log(json)
        // return
        setTimeout(() => {
            if(msg == ""){
            // console.log(json)
            $.post(path_url + "api/veiculos/store", json)
            .done((res) => {
                $('#modal_novo_veiculo .btn-close').trigger('click')

                console.log(res)
                swal("Sucesso", "Veículo cadastrado!", "success")

                var newOption = new Option(res.info, res.id, false, false);
                $('#veiculo_id').append(newOption);

                $("#modal_novo_veiculo").find('input, select').each(function () {
                    $(this).val('')
                })

            })
            .fail((err) => {
                console.log(err)
                swal("Erro", "Erro ao cadastrar veículo: " + err.responseJSON, "error")
                .then(() => {
                    $('#modal_novo_veiculo .btn-close').trigger('click')

                })
            })
        }else{
            swal("Alerta", msg, "warning")
        }
    }, 300)
    })
</script>

@endsection
