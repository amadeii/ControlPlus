@extends('layouts.app', ['title' => 'Produtos'])
@section('css')
<style type="text/css">
    .div-overflow {
        width: 180px;
        overflow-x: auto;
        white-space: nowrap;
    }
    @media (max-width: 768px) {
        .btns .btn{
            display: block;
            margin-top: 3px;
        }
    }
    .img-wrapper {
        height: 180px;
        overflow: hidden;
        border-top-left-radius: 1rem;
        border-top-right-radius: 1rem;
        background-color: #f8f9fa;
    }
    .produto-img {
        height: 100%;
        width: 100%;
        object-fit: cover;
        transition: transform 0.4s ease;
    }
    .produto-card {
        border-radius: 1rem;
        transition: all 0.3s ease;
        background-color: #fff;
    }
    .produto-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 28px rgba(0, 0, 0, 0.08);
    }
    .produto-card:hover .produto-img {
        transform: scale(1.05);
    }
    .produtos-table-area {
        width: 100%;
        max-width: 100%;
        overflow: hidden;
    }
    .produtos-table-wrapper {
        display: block;
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important;
        overflow-y: visible;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
    .produtos-table-wrapper table {
        min-width: 1800px;
        width: max-content !important;
    }
    .produtos-scroll-controls {
        display: flex !important;
        justify-content: flex-end;
        gap: 8px;
        margin-bottom: 8px;
    }
    .produtos-scroll-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border: 0;
        border-radius: 50%;
        background: #2563eb;
        color: #fff;
        font-size: 20px;
        box-shadow: 0 10px 24px rgba(37, 99, 235, .25);
    }

</style>
@endsection
@section('content')
<div class="mt-1">
    <div class="row">
        <div class="card">
            <div class="card-body">
                <div class="col-md-12 btns">
                    @can('produtos_create')
                    <a href="{{ route('produtos.create') }}" class="btn btn-success">
                        <i class="ri-add-circle-fill"></i>
                        Novo Produto
                    </a>
                    @endcan

                    <a href="{{ route('produtos.import') }}" class="btn btn-info pull-right">
                        <i class="ri-file-upload-line"></i>
                        Upload
                    </a>

                    <a href="{{ route('produtos.export') }}" class="btn btn-warning pull-right">
                        <i class="ri-file-excel-line"></i>
                        Exportar
                    </a>
                    @can('produtos_edit')
                    <a href="{{ route('produtos.reajuste') }}" class="btn btn-dark pull-right">
                        <i class="ri-file-edit-fill"></i>
                        Reajuste em Grupo
                    </a>

                    <a href="{{ route('produtos.alterar-valor-estoque') }}" class="btn btn-secondary pull-right">
                        <i class="ri-price-tag-3-line"></i>
                        Alterar Valor ou Estoque
                    </a>
                    @endif

                    <a href="{{ route('produtos.upload-imagens') }}" class="btn btn-light pull-right">
                        <i class="ri-image-fill"></i>
                        Upload de Imagens
                    </a>

                    @if($empresa->token_ibpt)
                    <a href="{{ route('produtos.ibpt') }}" class="btn btn-primary pull-right btn-ibpt">
                        <i class="ri-booklet-fill"></i>
                        Atualizar IBPT
                    </a>
                    @endif
                </div>
                <hr class="mt-3">

                <div class="col-lg-12">

                    <button class="btn btn-dark btn-toggle-filtros">
                        <i class="ri-filter-2-line"></i> Filtros
                    </button> 

                    {!!Form::open()->fill(request()->all())
                    ->get()->attrs(['class' => 'filtros-container'])
                    !!}
                    <div class="row mt-3 g-1">
                        <div class="col-md-2">
                            {!!Form::text('nome', 'Pesquisar por nome')
                            !!}
                        </div>

                        <div class="col-md-3 col-xl-2">
                            {!!Form::tel('codigo_barras', 'Pesquisar código de barras')
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('tipo', 'Tipo', ['' => 'Todos', 'composto' => 'Composto', 'variavel' => 'Variável', 'combo' => 'Combo'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('categoria_id', 'Categoria', ['' => 'Todos'] + $categorias->pluck('nome', 'id')->all())
                            ->attrs(['class' => 'form-select select2'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('marca_id', 'Marca', ['' => 'Todos'] + $marcas->pluck('nome', 'id')->all())
                            ->attrs(['class' => 'form-select select2'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::date('start_date', 'Dt. inicial cadastro')
                            !!}
                        </div>
                        <div class="col-md-2">
                            {!!Form::date('end_date', 'Dt. final cadastro')
                            !!}
                        </div>

                        @if(__countLocalAtivo() > 1)
                        <div class="col-md-2">
                            {!!Form::select('local_id', 'Local', __getLocaisAtivoUsuarioParaSelect())
                            ->attrs(['class' => 'select2'])
                            !!}
                        </div>
                        @endif

                        <div class="col-md-2">
                            {!!Form::select('com_variacao', 'Com variação', ['' => 'Todos', 1 => 'Sim', 0 => 'Não'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('com_imagem', 'Com imagem', ['' => 'Todos', 1 => 'Sim', 0 => 'Não'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('status', 'Ativo', ['' => 'Todos', 1 => 'Sim', 0 => 'Não'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('gerenciar_estoque', 'Gerenciando estoque', ['' => 'Todos', 1 => 'Sim', -1 => 'Não'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>

                        <div class="col-md-2">
                            {!!Form::select('ordem', 'Ordenar por', ['nome' => 'Nome', 'numero_sequencial' => 'Código', 'created_at' => 'Data de cadastro'])
                            ->attrs(['class' => 'form-select'])
                            !!}
                        </div>
                        <div class="col-md-4 col-xl-2 text-left">
                            <br>
                            <button class="btn btn-primary" type="submit"> <i class="ri-search-line"></i>Pesquisar</button>
                            <a id="clear-filter" class="btn btn-danger" href="{{ route('produtos.index') }}"><i class="ri-eraser-fill"></i>Limpar</a>
                        </div>
                    </div>
                    {!!Form::close()!!}
                </div>

                @if($tipoExibe == 'tabela')
                @include('produtos.partials.tabela')
                @else
                @include('produtos.partials.card')
                @endif

                <br>
                <div class="row">
                    <div class="col-md-2">
                        @can('produtos_delete')
                        <form action="{{ route('produtos.destroy-select') }}" method="post" id="form-delete-select">
                            @method('delete')
                            @csrf
                            <div></div>
                            <button type="button" class="btn btn-danger btn-sm btn-delete-all w-100" disabled>
                                <i class="ri-close-circle-line"></i> Remover selecionados
                            </button>
                        </form>
                        @endcan
                    </div>
                    <div class="col-md-2">
                        <form action="{{ route('produtos.desactive-select') }}" method="post" id="form-desactive-select">
                            @csrf
                            <div></div>
                            <button type="button" class="btn btn-warning btn-sm btn-desactive-all w-100" disabled>
                                <i class="ri-close-circle-line"></i> Desativar selecionados
                            </button>
                        </form>
                    </div>
                </div>
                <br>
                {!! $data->appends(request()->all())->links() !!}
            </div>
        </div>
    </div>
</div>

@include('modals._info_vencimento', ['not_submit' => true])
@include('produtos.partials.modal_info')

@endsection

@section('js')
<script type="text/javascript" src="/js/delete_selecionados.js"></script>

<script type="text/javascript">
    function infoVencimento(id) {
        $.get(path_url + 'api/produtos/info-vencimento/' + id)
        .done((res) => {
            $('.table-infoValidade tbody').html(res)
        })
        .fail((e) => {
            console.log(e)
        })
    }

    $('.btn-ibpt').click(() => {
        $body = $("body");
        $body.addClass("loading");
    })

    function openModal(id) {
        $.get(path_url + "api/produtos/modal/"+id)
        .done((data) => {
            // console.log(data)
            $('#modal-info').modal('show')
            $('#modal-info .modal-content').html(data)
        })
        .fail((e) => {
            console.log(e)
        })
    }

    function setupProdutosTableScroll() {
        const wrapper = document.querySelector('.produtos-table-wrapper');
        const controls = document.querySelector('.produtos-scroll-controls');
        const leftButton = document.querySelector('.produtos-scroll-left');
        const rightButton = document.querySelector('.produtos-scroll-right');

        if (!wrapper || !controls || !leftButton || !rightButton) {
            return;
        }

        const scrollTable = (direction) => {
            wrapper.scrollBy({
                left: 500 * direction,
                behavior: 'smooth'
            });
        };

        leftButton.addEventListener('click', () => scrollTable(-1));
        rightButton.addEventListener('click', () => scrollTable(1));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', setupProdutosTableScroll);
    } else {
        setupProdutosTableScroll();
    }

</script>
@endsection
