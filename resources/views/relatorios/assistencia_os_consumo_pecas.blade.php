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

@include('exports.relatorio_assistencia_os_pecas', ['data' => $data])

@endsection
