@extends('layouts.app', ['title' => 'Auditoria operacional'])

@section('content')
<div class="mt-1">
    <div class="row">
        <div class="card">
            <div class="card-body">
                <p class="text-muted">Registros filtrados pela empresa atual. Requer permissão de visualização de logs.</p>

                @php
                    $filtrosComunsTab = $filtrosComuns ?? [];
                    $filtrosAcaoTab = $filtrosAcao ?? [];
                @endphp

                <ul class="nav nav-tabs mb-3">
                    <li class="nav-item">
                        <a class="nav-link {{ ($aba ?? 'acao') === 'acao' ? 'active' : '' }}"
                           href="{{ route('auditoria-operacional.index', array_merge($filtrosComunsTab, $filtrosAcaoTab, ['aba' => 'acao'])) }}">Linhas de ação</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ ($aba ?? '') === 'estoque' ? 'active' : '' }}"
                           href="{{ route('auditoria-operacional.index', array_merge($filtrosComunsTab, ['aba' => 'estoque'])) }}">Movimentações de estoque</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ ($aba ?? '') === 'os' ? 'active' : '' }}"
                           href="{{ route('auditoria-operacional.index', array_merge($filtrosComunsTab, ['aba' => 'os'])) }}">Ordens de serviço</a>
                    </li>
                </ul>

                {!! Form::open()->fill(request()->all())->get() !!}
                <input type="hidden" name="aba" value="{{ $aba ?? 'acao' }}">

                <div class="row g-2 mb-3">
                    <div class="col-md-2">
                        {!! Form::date('start_date', 'Data inicial') !!}
                    </div>
                    <div class="col-md-2">
                        {!! Form::date('end_date', 'Data final') !!}
                    </div>
                    @if(($aba ?? 'acao') === 'acao')
                    <div class="col-md-2">
                        {!! Form::select('acao', 'Ação', \App\Models\AcaoLog::acoes())->attrs(['class' => 'form-select']) !!}
                    </div>
                    <div class="col-md-3">
                        {!! Form::select('local', 'Módulo (local)', \App\Models\AcaoLog::locais())->attrs(['class' => 'select2']) !!}
                    </div>
                    @endif
                    <div class="col-md-2 d-flex align-items-end">
                        <button class="btn btn-primary" type="submit"><i class="ri-search-line"></i> Filtrar</button>
                        <a class="btn btn-danger ms-1" href="{{ route('auditoria-operacional.index', ['aba' => $aba ?? 'acao']) }}"><i class="ri-eraser-fill"></i></a>
                    </div>
                </div>
                {!! Form::close() !!}

                @if(($aba ?? 'acao') === 'acao' && $acaoLogs)
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Local</th>
                                <th>Ação</th>
                                <th>Descrição</th>
                                <th>Usuário</th>
                                <th>IP</th>
                                <th>Sessão</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($acaoLogs as $row)
                            <tr>
                                <td>{{ $row->local }}</td>
                                <td>{{ $row->acao }}</td>
                                <td style="max-width: 340px;"><span title="{{ $row->descricao }}">{{ \Illuminate\Support\Str::limit($row->descricao, 140) }}</span></td>
                                <td>@if($row->user){{ $row->user->email ?? $row->user->name ?? ('#'.$row->user_id) }} @else — @endif</td>
                                <td><small>{{ $row->ip_address ?? '—' }}</small></td>
                                <td><small class="font-monospace">@if($row->session_id){{ substr($row->session_id, 0, 10) }}… @else — @endif</small></td>
                                <td><small>{{ __data_pt($row->created_at) }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {!! $acaoLogs->links() !!}
                @elseif(($aba ?? '') === 'estoque' && $estoqueAudits)
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Produto</th>
                                <th>Tipo</th>
                                <th>Transação</th>
                                <th>Qtd</th>
                                <th>Saldo antes</th>
                                <th>Saldo depois</th>
                                <th>Usuário (mov.)</th>
                                <th>IP</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($estoqueAudits as $row)
                            <tr>
                                <td>@if($row->produto){{ $row->produto->nome }} @else #{{ $row->produto_id }} @endif</td>
                                <td>{{ $row->tipo }}</td>
                                <td><small>{{ $row->tipo_transacao }} / {{ $row->codigo_transacao }}</small></td>
                                <td>{{ $row->quantidade_movimentada }}</td>
                                <td>{{ $row->estoque_antes }}</td>
                                <td>{{ $row->estoque_depois }}</td>
                                <td>#{{ $row->user_id ?? '—' }}</td>
                                <td><small>{{ $row->ip_address ?? '—' }}</small></td>
                                <td><small>{{ __data_pt($row->created_at) }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {!! $estoqueAudits->links() !!}
                @elseif(($aba ?? '') === 'os' && $osAudits)
                <div class="table-responsive">
                    <table class="table table-sm table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Evento</th>
                                <th>OS</th>
                                <th>Usuário</th>
                                <th>IP</th>
                                <th>Motivo / resumo</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($osAudits as $row)
                            <tr>
                                <td>{{ $row->evento }}</td>
                                <td>
                                    @if($row->ordemServico)
                                        #{{ $row->ordemServico->codigo_sequencial }}
                                    @elseif(is_array($row->snapshot_exclusao_json) && isset($row->snapshot_exclusao_json['codigo_sequencial']))
                                        #{{ $row->snapshot_exclusao_json['codigo_sequencial'] }} <span class="text-muted small">(excluída)</span>
                                    @elseif($row->ordem_servico_id)
                                        #{{ $row->ordem_servico_id }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>@if($row->user){{ $row->user->email ?? $row->user->name ?? ('#'.$row->user_id) }} @else — @endif</td>
                                <td><small>{{ $row->ip_address ?? '—' }}</small></td>
                                <td style="max-width:360px;">
                                    @if($row->evento === 'delete')
                                        <span title="{{ $row->motivo_auditoria }}">{{ \Illuminate\Support\Str::limit($row->motivo_auditoria ?? '', 160) }}</span>
                                    @else
                                        <small>@if(is_array($row->diff_json) && count($row->diff_json) > 0){{ count($row->diff_json) }} campo(s) @else alteração registrada @endif</small>
                                        @if(is_array($row->diff_json) && count($row->diff_json) > 0)
                                        <details class="mt-1">
                                            <summary class="cursor-pointer small">detalhar</summary>
                                            <pre class="small bg-light p-2 mt-1 mb-0" style="white-space: pre-wrap;">{{ json_encode($row->diff_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                        </details>
                                        @endif
                                    @endif
                                </td>
                                <td><small>{{ __data_pt($row->created_at) }}</small></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {!! $osAudits->links() !!}
                @else
                <p class="text-muted">Nenhuma aba selecionada.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
