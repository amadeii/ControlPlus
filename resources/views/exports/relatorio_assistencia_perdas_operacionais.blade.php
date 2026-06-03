<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>ID</th>
            <th>Data</th>
            <th>Peça</th>
            <th>Código</th>
            <th>Qtd</th>
            <th>Motivo</th>
            <th>Observação</th>
            <th>Depósito</th>
            <th>Usuário</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($data as $key => $row)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $row->id }}</td>
                <td>{{ isset($row->created_at) ? __data_pt($row->created_at, true) : '' }}</td>
                <td>{{ $row->produto_nome ?? '' }}</td>
                <td>{{ $row->codigo_barras ?? '' }}</td>
                <td>{{ $row->quantidade }}</td>
                <td>{{ $row->motivo_label ?? ($row->motivo ?? '') }}</td>
                <td>{{ \Illuminate\Support\Str::limit($row->observacao ?? '', 120) }}</td>
                <td>{{ $row->deposito_nome }}</td>
                <td>{{ $row->usuario_nome }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
