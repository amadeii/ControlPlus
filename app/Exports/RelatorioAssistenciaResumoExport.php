<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class RelatorioAssistenciaResumoExport implements FromView
{
    public function __construct(
        private bool $empresaSemAssistencia,
        private int $totalOs,
        private ?float $leadDiasMedio,
        private int $leadAmostra,
        private $porEstado,
        private $porResponsavel,
    ) {
    }

    public function view(): View
    {
        return view('exports.relatorio_assistencia_resumo_operacional', [
            'empresaSemAssistencia' => $this->empresaSemAssistencia,
            'totalOs' => $this->totalOs,
            'leadDiasMedio' => $this->leadDiasMedio,
            'leadAmostra' => $this->leadAmostra,
            'porEstado' => $this->porEstado,
            'porResponsavel' => $this->porResponsavel,
        ]);
    }
}
