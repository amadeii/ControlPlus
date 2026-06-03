<table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom:10px; width: 100%;">
    <thead>
        <tr>
            <th>OS</th>
            <th>Estado</th>
            <th>Fase técnica</th>
            <th>Valor</th>
            <th>Início</th>
            <th>Entrega</th>
            <th>Técnico resp.</th>
            <th>Resp. cadastro OS</th>
            <th>Equipamento</th>
            <th>Série</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $key => $os)
            <tr class="@if ($key % 2 == 0) pure-table-odd @endif">
                <td>{{ $os->codigo_sequencial }}</td>
                <td>{{ $os->estado_label }}</td>
                <td>{{ $os->fase_label }}</td>
                <td>{{ __moeda($os->valor) }}</td>
                <td>@if($os->data_inicio){{ __data_pt($os->data_inicio, true) }}@endif</td>
                <td>@if($os->data_entrega){{ __data_pt($os->data_entrega, true) }}@else — @endif</td>
                <td>{{ $os->tecnico_nome }}</td>
                <td>{{ $os->responsavel_os_nome }}</td>
                <td>{{ $os->equipamento }}</td>
                <td>{{ $os->numero_serie }}</td>
            </tr>
        @empty
            <tr><td colspan="10">
                @if($tplAssistenciaOff)
                    Indisponível: tipo OS diferente de Assistência técnica.
                @else
                    Nenhuma OS interna no período.
                @endif
            </td></tr>
        @endforelse
    </tbody>
</table>
