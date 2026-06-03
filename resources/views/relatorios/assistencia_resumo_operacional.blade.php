@extends('relatorios.default')
@section('content')

<style type="text/css">
    tbody td {
        text-align: left !important;
    }
    th {
        text-align: left !important;
    }
</style>

@include('exports.relatorio_assistencia_resumo_operacional', [
    'empresaSemAssistencia' => $empresaSemAssistencia,
    'totalOs' => $totalOs,
    'leadDiasMedio' => $leadDiasMedio,
    'leadAmostra' => $leadAmostra,
    'porEstado' => $porEstado,
    'porResponsavel' => $porResponsavel,
])

@endsection
