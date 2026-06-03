@if (!empty($empresaSemAssistencia) && $empresaSemAssistencia)
    <p class="small text-muted">Disponível quando o tipo da ordem de serviço da empresa está em <strong>Assistência técnica</strong> (configurações gerais).</p>
@else
    <p><strong>Total de OS no período (filtros aplicados):</strong> {{ (int) $totalOs }}</p>

    @if ($leadAmostra > 0)
        <p>
            <strong>Lead time médio</strong> (dias corridos entre início e entrega prevista/realizada, apenas OS com ambas as datas):
            {{ $leadDiasMedio !== null ? number_format($leadDiasMedio, 1, ',', '.') . ' dias' : '—' }}
            <span class="small text-muted">({{ $leadAmostra }} OS)</span>
        </p>
    @else
        <p class="small text-muted">Lead time médio não calculado: nenhuma OS no período com <code>data_inicio</code> e <code>data_entrega</code> preenchidas.</p>
    @endif

    <h5 class="mt-3">Volume por estado</h5>
    <table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom: 10px; width: 100%;">
        <thead>
            <tr>
                <th>Estado</th>
                <th style="text-align: right;">Quantidade</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porEstado as $idx => $row)
                <tr class="@if ($idx % 2 == 0) pure-table-odd @endif">
                    <td>{{ $row->estado_label ?? $row->estado }}</td>
                    <td style="text-align: right;">{{ (int) $row->total }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Nenhuma OS no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h5 class="mt-3">Volume por responsável</h5>
    <table class="table-sm table-borderless" style="border-bottom: 1px solid rgb(206, 206, 206); margin-bottom: 10px; width: 100%;">
        <thead>
            <tr>
                <th>Responsável</th>
                <th style="text-align: right;">Quantidade</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($porResponsavel as $idx => $row)
                <tr class="@if ($idx % 2 == 0) pure-table-odd @endif">
                    <td>{{ $row->nome }}</td>
                    <td style="text-align: right;">{{ (int) $row->total }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">Nenhuma OS no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="small text-muted mb-0">MVP: sem tempos por etapa de fluxo — apenas indicadores consolidados pela data de início da OS.</p>
@endif
