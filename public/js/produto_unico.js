function modalData(id) {
    $('#dados_produto_unico').modal('show')

    $.get(path_url + 'api/produtos/dados-produto-unico/' + id)
    .done((res) => {
        $('#dados_produto_unico .modal-body').html(res)
    })
    .fail((e) => {
        console.log(e)
    })
}

$(function () {
    let produtoConsulta = $('.produto-consulta-codigo-produto')
    let codigoConsulta = $('.produto-consulta-codigo-codigo')
    let queryParams = new URLSearchParams(window.location.search)

    function valorFiltro(id) {
        return $('#' + id).val() || queryParams.get(id)
    }

    if (produtoConsulta.length) {
        if (produtoConsulta.hasClass('select2-hidden-accessible')) {
            produtoConsulta.select2('destroy')
        }

        produtoConsulta.select2({
            minimumInputLength: 2,
            language: 'pt-BR',
            placeholder: 'Pesquisar por produto',
            ajax: {
                cache: true,
                url: path_url + 'produto-consulta-codigo/produtos',
                dataType: 'json',
                data: function (params) {
                    return {
                        pesquisa: params.term,
                        empresa_id: valorFiltro('empresa_id'),
                        local_id: valorFiltro('local_id')
                    }
                },
                processResults: function (res) {
                    return res
                }
            }
        })
    }

    if (codigoConsulta.length) {
        if (codigoConsulta.hasClass('select2-hidden-accessible')) {
            codigoConsulta.select2('destroy')
        }

        codigoConsulta.select2({
            minimumInputLength: 0,
            language: 'pt-BR',
            placeholder: 'Pesquisar por codigo',
            ajax: {
                cache: true,
                url: path_url + 'produto-consulta-codigo/codigos',
                dataType: 'json',
                data: function (params) {
                    return {
                        pesquisa: params.term,
                        produto_id: produtoConsulta.val(),
                        empresa_id: valorFiltro('empresa_id'),
                        local_id: valorFiltro('local_id')
                    }
                },
                processResults: function (res) {
                    return res
                }
            }
        })

        produtoConsulta.on('change', function () {
            codigoConsulta.val(null).trigger('change')
        })
    }
})
