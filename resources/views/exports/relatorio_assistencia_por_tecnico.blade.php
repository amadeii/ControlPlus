<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>Técnico responsável</th>
            <th>Quantidade OS</th>
            <th>Soma valor OS</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $key => $r)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $r->tecnico_nome }}</td>
                <td>{{ $r->qtd_os }}</td>
                <td>{{ __moeda($r->soma_valor ?? 0) }}</td>
            </tr>
        @empty
            <tr><td colspan="3">
                @if ($tplAssistenciaOff || $empresaSemAssistencia)
                    Indisponível: assistência técnica não configurada ou estoque não integrado.
                @else
                    Nenhuma OS no período.
                @endif
            </td></tr>
        @endforelse
    </tbody>
</table>
<p style="font-size: 10px;">Campo técnico: <code>tecnico_responsavel_id</code> na OS.</p>
