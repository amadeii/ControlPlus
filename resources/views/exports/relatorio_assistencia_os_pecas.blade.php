<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>OS</th>
            <th>Cliente</th>
            <th>Equipamento</th>
            <th>Série</th>
            <th>Peça</th>
            <th>Cód.</th>
            <th>Qtd</th>
            <th>Vlr. unit. OS</th>
            <th>Movimento</th>
            <th>Depósito</th>
            <th>Usuário</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $key => $row)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $row->codigo_sequencial }}</td>
                <td>{{ $row->cliente_nome }}</td>
                <td>{{ $row->equipamento }}</td>
                <td>{{ $row->numero_serie }}</td>
                <td>{{ $row->produto_nome }}</td>
                <td>{{ $row->codigo_barras }}</td>
                <td>{{ $row->quantidade }}</td>
                <td>{{ __moeda($row->valor_unitario_os) }}</td>
                <td>{{ isset($row->movimentado_em) ? __data_pt($row->movimentado_em, true) : '' }}</td>
                <td>{{ $row->deposito_nome }}</td>
                <td>{{ $row->usuario_nome }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
