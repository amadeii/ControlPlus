<?php

namespace App\Http\Controllers;

use App\Models\AcaoLog;
use App\Models\AuditEstoqueDetalhe;
use App\Models\AuditOrdemServicoAlteracao;
use Illuminate\Http\Request;

class AuditoriaOperacionalController extends Controller
{
    public function __construct()
    {
        $this->middleware('permission:logs_view');
    }

    public function index(Request $request)
    {
        $empresaId = (int) $request->empresa_id;
        $start = $request->get('start_date');
        $end = $request->get('end_date');
        $aba = $request->get('aba', 'acao');
        $abasValidas = ['acao', 'estoque', 'os'];
        if (!in_array($aba, $abasValidas, true)) {
            $aba = 'acao';
        }
        $acao = $request->get('acao');
        $local = $request->get('local');

        $filtroData = static function ($query) use ($start, $end) {
            if (!empty($start)) {
                $query->whereDate('created_at', '>=', $start);
            }
            if (!empty($end)) {
                $query->whereDate('created_at', '<=', $end);
            }
        };

        $filtrosComuns = array_filter([
            'start_date' => $start,
            'end_date' => $end,
        ], static fn ($value) => !empty($value));

        $filtrosAcao = array_filter([
            'acao' => $acao,
            'local' => $local,
        ], static fn ($value) => !empty($value));

        $paginationBase = array_merge($filtrosComuns, ['aba' => $aba]);

        $acaoLogs = null;
        $estoqueAudits = null;
        $osAudits = null;

        if ($aba === 'acao') {
            $acaoLogs = AcaoLog::with(['empresa', 'user'])
                ->where('empresa_id', $empresaId)
                ->tap($filtroData)
                ->when(!empty($acao), fn ($q) => $q->where('acao', $acao))
                ->when(!empty($local), fn ($q) => $q->where('local', $local))
                ->orderByDesc('created_at')
                ->paginate(40)
                ->appends(array_merge($paginationBase, $filtrosAcao));
        } elseif ($aba === 'estoque') {
            $estoqueAudits = AuditEstoqueDetalhe::with(['produto:id,nome'])
                ->where('empresa_id', $empresaId)
                ->tap($filtroData)
                ->orderByDesc('created_at')
                ->paginate(40)
                ->appends($paginationBase);
        } else {
            $osAudits = AuditOrdemServicoAlteracao::with(['ordemServico:id,codigo_sequencial', 'user'])
                ->where('empresa_id', $empresaId)
                ->tap($filtroData)
                ->orderByDesc('created_at')
                ->paginate(40)
                ->appends($paginationBase);
        }

        return view('auditoria_operacional.index', compact(
            'acaoLogs',
            'estoqueAudits',
            'osAudits',
            'aba',
            'filtrosComuns',
            'filtrosAcao'
        ));
    }
}
