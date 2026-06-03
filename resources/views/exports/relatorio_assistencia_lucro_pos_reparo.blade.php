<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>OS</th>
            <th>Data entrega</th>
            <th>Receita (OS)</th>
            <th>Custo peças*</th>
            <th>Lucro estimado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $key => $row)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $row->codigo_sequencial }}</td>
                <td>@if($row->data_entrega){{ __data_pt($row->data_entrega, true) }}@else — @endif</td>
                <td>{{ __moeda($row->receita) }}</td>
                <td>{{ __moeda($row->custo_pecas) }}</td>
                <td>{{ __moeda($row->lucro_estimado) }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    @if($tplAssistenciaOff)
                        Indisponível: configure o tipo de OS como <strong>Assistência técnica</strong> em configuração geral.
                    @else
                        Nenhuma OS <strong>finalizada</strong> no período com data de entrega (ou atualização) no intervalo.
                    @endif
                </td>
            </tr>
        @endforelse
    </tbody>
    @if(!$tplAssistenciaOff && $data->isNotEmpty())
    <tfoot>
        <tr>
            <th colspan="2">Totais</th>
            <th>{{ __moeda($totaisObj->receita) }}</th>
            <th>{{ __moeda($totaisObj->custo) }}</th>
            <th>{{ __moeda($totaisObj->lucro) }}</th>
        </tr>
    </tfoot>
    @endif
</table>
<p style="font-size: 10px;">* Custo = soma (quantidade × valor de compra do cadastro do produto) nas linhas da OS com produto vinculado. Linhas somente texto não entram. Serviços não entram no custo aqui. O campo receita usa o valor total da OS.</p>
