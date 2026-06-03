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

@if ($empresaSemAssistencia)
    <p class="text-muted">Este relatório só se aplica a empresas com tipo de OS em Assistência técnica.</p>
@else
    @include('exports.relatorio_assistencia_perdas_operacionais', ['data' => $data])
@endif

@endsection
