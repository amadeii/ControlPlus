TOTALOLD = 0
$(function(){
	TOTALOLD = $('#valor_total_old').val()
	setTimeout(atualizarFinalizacaoTroca, 300)
	setTimeout(atualizarFinalizacaoTroca, 900)

})

/**
 * Devolução PDV não exige item novo.
 * 1) window.CP_TROCA_IS_DEVOLUCAO_PDV vem do Blade (confiável).
 * 2) Depois: hidden, data-* na página, query string, input[name=modalidade].
 */
function trocaIsDevolucaoPdv() {
	if (window.CP_TROCA_IS_DEVOLUCAO_PDV === true) {
		return true
	}
	var m = String($("#inp-modalidade").val() || "").trim()
	if (m === "devolucao_pdv") {
		return true
	}
	m = String($("#form-troca [data-troca-modalidade]").attr("data-troca-modalidade") || "").trim()
	if (m === "devolucao_pdv") {
		return true
	}
	m = String($('#form-troca input[name="modalidade"]').val() || "").trim()
	if (m === "devolucao_pdv") {
		return true
	}
	var el = document.getElementById("inp-modalidade")
	if (el && String(el.value || "").trim() === "devolucao_pdv") {
		return true
	}
	try {
		if (new URLSearchParams(window.location.search).get("modalidade") === "devolucao_pdv") {
			return true
		}
	} catch (e) {}
	return false
}

function comparaValor(){
	setTimeout(() => {
		let total = convertMoedaToFloat($('#inp-valor_total').val())
		TOTALOLD = parseFloat(TOTALOLD)
		if(total > TOTALOLD){
			$('.h-valor_pagar').removeClass('d-none')
			$('.h-valor_restante').addClass('d-none')
			$('.valor_pagar').text('R$ ' + convertFloatToMoeda(total - TOTALOLD))
			$('#inp-valor_pagar').val(total - TOTALOLD)
			$('#inp-valor_credito').val('0')
		}else if(total < TOTALOLD){
			$('.h-valor_pagar').addClass('d-none')
			$('.h-valor_restante').removeClass('d-none')
			$('.valor_restante').text('R$ ' + convertFloatToMoeda(TOTALOLD - total))
			$('#inp-valor_pagar').val('0')
			$('#inp-valor_credito').val(TOTALOLD - total)
		}else{
			$('.valor_pagar').text('R$ ' + convertFloatToMoeda(0))
			$('.h-valor_pagar').removeClass('d-none')
			$('.h-valor_restante').addClass('d-none')
			$('.valor_restante').text('R$ ' + convertFloatToMoeda(0))
			$('#inp-valor_pagar').val('0')
			$('#inp-valor_credito').val('0')
		}
	}, 500)
}

$("body").on("click", ".cards-categorias, .card-group", function () {
	comparaValor()
})

$("body").on("click", ".btn-add-item", function () {
	comparaValor()
})

$("body").on("click", ".btn-qtd", function () {
	comparaValor()
})

$("body").on('click', '.salvar_troca', function () {
	let cliente = $("#inp-cliente_id").val();
	if(!cliente){
		var isDev = trocaIsDevolucaoPdv()
		swal("Alerta", isDev ? "Informe o cliente para finalizar a devolução!" : "Informe o cliente para finalizar a troca!", "warning")
		.then(() => {
			setTimeout(() => {
				$('#finalizar_troca .btn-close').trigger('click')
			}, 100)
		})
		
	}
})
$(".table-itens").on('click', '.btn-delete-row', function () {
	comparaValor()		
})

$("body").on('change', '#inp-tipo_pagamento', function () {
	let tipo_pagamento = $(this).val()
	let cliente = $("#inp-cliente_id").val();
	atualizarFinalizacaoTroca()
	
	if(tipo_pagamento == '00' && !cliente){
		$(this).val('').change()
		swal("Alerta", "Informe o cliente!", "warning")
		$('#cliente').modal('show')
	}
});

$("body").on('click', '#btn-comprovante-troca', function () {
	$("#form-troca").submit()
})

$("#form-troca").on("submit", function (e) {
	e.preventDefault();
	var isDev = trocaIsDevolucaoPdv()
	if (trocaExigePagamento() && !String($("#inp-tipo_pagamento").val() || "").trim()) {
		swal("Atenção", "Selecione a forma de pagamento para finalizar a troca.", "warning");
		return;
	}
	if (typeof validateCodigoUnicoRows === "function" && !validateCodigoUnicoRows()) {
		return;
	}
	if (!isDev) {
		var nOrig = parseInt($("#troca_linhas_venda_origem").val() || "0", 10);
		if ($(".table-itens tbody tr").length <= nOrig) {
			swal("Atenção", "Inclua ao menos um produto novo (saída de estoque) na troca antes de finalizar.", "warning");
			return;
		}
	}
	const form = $(e.target);
	var json = $(this).serializeFormJSON();
	normalizarItensTrocaPayload(json)

	json.empresa_id = $('#empresa_id').val()
	json.usuario_id = $('#usuario_id').val()
	json.venda_id = $('#venda_id').val()
	json.tipo = $('#tipo').val()
	json.modalidade = isDev ? "devolucao_pdv" : (String($("#inp-modalidade").val() || "").trim() || "troca")
	if (trocaDiferencaZero()) {
		json.tipo_pagamento = ""
	}

	$.post(path_url + 'api/trocas/store', json)
	.done((success) => {
		swal({
			title: "Sucesso",
			text: (json.modalidade === "devolucao_pdv" ? "Devolução registrada" : "Troca finalizada") + " com sucesso, deseja imprimir o comprovante?",
			icon: "success",
			buttons: true,
			buttons: ["Não", "Sim"],
			dangerMode: true,
		}).then((isConfirm) => {
			if (isConfirm) {
				window.open(path_url + 'trocas/imprimir/' + success.id, "_blank")
			} else {
			}

			location.href = '/trocas';
		});

	}).fail((err) => {
		let message = (err.responseJSON && (typeof err.responseJSON === "string" ? err.responseJSON : err.responseJSON.message)) || err.responseText || "Não foi possível concluir a operação."
		if (typeof message !== "string") {
			message = JSON.stringify(message)
		}
		swal("Erro", message, "error")
		console.log(err)
	})
})

function moedaTrocaToFloat(value) {
	if (typeof convertMoedaToFloat === "function") {
		var parsed = convertMoedaToFloat(String(value || "0"))
		return isNaN(parsed) ? 0 : parsed
	}
	var normalized = String(value || "0").replace(/\./g, "").replace(",", ".").replace(/[^0-9.\-]/g, "")
	var parsedFallback = parseFloat(normalized)
	return isNaN(parsedFallback) ? 0 : parsedFallback
}

function tipoLinhaTrocaAtual(row) {
	var $row = row && row.jquery ? row : $(row)
	return String(
		$row.attr("data-tipo-linha") ||
		$row.data("tipo-linha") ||
		$row.find('input[name="tipo_linha[]"]').val() ||
		"saida"
	).trim()
}

function saldoTrocaAtual() {
	var valorPagar = moedaTrocaToFloat($("#inp-valor_pagar").val())
	var valorCredito = moedaTrocaToFloat($("#inp-valor_credito").val())
	if (Math.abs(valorPagar) >= 0.005 || Math.abs(valorCredito) >= 0.005) {
		return valorPagar - valorCredito
	}

	var totalRetorno = 0
	var totalSaida = 0
	$(".table-itens tbody tr.line-product").each(function () {
		var row = $(this)
		var subtotal = moedaTrocaToFloat(
			row.find('input[name="subtotal_item[]"]').val() ||
			row.find(".subtotal-item").val()
		)
		if (tipoLinhaTrocaAtual(row) === "retorno") {
			totalRetorno += subtotal
		} else {
			totalSaida += subtotal
		}
	})
	return totalSaida - totalRetorno
}

function trocaDiferencaZero() {
	return Math.abs(saldoTrocaAtual()) < 0.005
}

function trocaExigePagamento() {
	return saldoTrocaAtual() > 0.005
}

function atualizarFinalizacaoTroca() {
	var isZero = trocaDiferencaZero()
	var tipoPagamento = String($("#inp-tipo_pagamento").val() || "").trim()
	if (isZero) {
		$("#inp-tipo_pagamento").val("")
		$("#salvar_venda").removeAttr("disabled")
		return
	}
	if (!trocaExigePagamento() || tipoPagamento) {
		$("#salvar_venda").removeAttr("disabled")
	} else {
		$("#salvar_venda").attr("disabled", 1)
	}
}

function normalizarItensTrocaPayload(json) {
	var produtoIds = []
	var tipoLinhas = []
	var quantidades = []
	var valoresUnitarios = []
	var subtotais = []
	var codigoUnicoIds = []
	var variacoes = []

	$(".table-itens tbody tr.line-product").each(function () {
		var row = $(this)
		produtoIds.push(row.find('input[name="produto_id[]"]').val() || row.find(".produto_row").val() || "")
		tipoLinhas.push(
			String(
				row.attr("data-tipo-linha") ||
				row.data("tipo-linha") ||
				row.find('input[name="tipo_linha[]"]').val() ||
				""
			).trim()
		)
		quantidades.push(row.find('input[name="quantidade[]"]').val() || row.find(".qtd_row").val() || "")
		valoresUnitarios.push(row.find('input[name="valor_unitario[]"]').val() || row.find(".value-unit").val() || "")
		subtotais.push(row.find('input[name="subtotal_item[]"]').val() || row.find(".subtotal-item").val() || "")
		codigoUnicoIds.push(row.find(".codigo_unico_ids").val() || "")
		variacoes.push(row.find('input[name="variacao_id[]"]').val() || "")
	})

	json.produto_id = produtoIds
	json.tipo_linha = tipoLinhas
	json.quantidade = quantidades
	json.valor_unitario = valoresUnitarios
	json.subtotal_item = subtotais
	json.codigo_unico_ids = codigoUnicoIds
	json.variacao_id = variacoes

	delete json["produto_id[]"]
	delete json["tipo_linha[]"]
	delete json["quantidade[]"]
	delete json["valor_unitario[]"]
	delete json["subtotal_item[]"]
	delete json["codigo_unico_ids[]"]
	delete json["variacao_id[]"]

	console.debug("[troca] payload itens normalizado", {
		produto_id: produtoIds,
		tipo_linha: tipoLinhas,
		codigo_unico_ids: codigoUnicoIds,
	})
}
