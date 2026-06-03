<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>#</th>
            <th>Peça</th>
            <th>Cód. barras</th>
            <th>Qtd consumida</th>
            <th>OS distintas</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $key => $row)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $key + 1 }}</td>
                <td>{{ $row->produto_nome }}</td>
                <td>{{ $row->codigo_barras }}</td>
                <td>{{ $row->qtd_total }}</td>
                <td>{{ $row->os_distintas }}</td>
            </tr>
        @empty
            <tr><td colspan="5">
                @if ($empresaSemAssistencia)
                    Indisponível: integração de estoque de assistência inativa.
                @else
                    Nenhum consumo <code>os_consumo_peca</code> no período.
                @endif
            </td></tr>
        @endforelse
    </tbody>
</table>
<p style="font-size: 10px;">Top {{ $limite }} produtos por quantidade movimentada (baixa de assistência).</p>
