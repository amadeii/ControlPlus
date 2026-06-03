<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>Inv. trade-in #</th>
            <th>Descrição item</th>
            <th>Série</th>
            <th>Trade — item</th>
            <th>Lançamentos</th>
            <th>Qtd peças</th>
            <th>Σ incremento custo</th>
            <th>Valor atual item</th>
            <th>Último lanç.</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $key => $row)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $row->inventario_item_id }}</td>
                <td>{{ $row->inventario_descricao }}</td>
                <td>{{ $row->inventario_serial }}</td>
                <td>{{ $row->tradein_nome_item ?? '—' }}</td>
                <td>{{ $row->qtd_lancamentos }}</td>
                <td>{{ $row->qtd_total_pecas_consumidas }}</td>
                <td>{{ __moeda($row->total_incremento) }}</td>
                <td>{{ __moeda($row->inventario_valor_atual) }}</td>
                <td>@if($row->ultimo_lanc_em){{ __data_pt($row->ultimo_lanc_em, true) }}@endif</td>
            </tr>
        @empty
            @if (!empty($bloqueado))
                <tr><td colspan="9">É necessária a permissão <strong>tradein_view</strong> para ver este relatório.</td></tr>
            @elseif ($empresaSemAssistencia || $tplAssistenciaOff)
                <tr><td colspan="9">Indisponível: empresa sem integração de estoque de assistência ou tipo de OS diferente de Assistência técnica.</td></tr>
            @else
                <tr><td colspan="9">Nenhum lançamento no período.</td></tr>
            @endif
        @endforelse
    </tbody>
</table>
