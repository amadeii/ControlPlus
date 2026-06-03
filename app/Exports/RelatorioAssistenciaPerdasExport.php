<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class RelatorioAssistenciaPerdasExport implements FromView
{
    public function __construct(private $data)
    {
    }

    public function view(): View
    {
        return view('exports.relatorio_assistencia_perdas_operacionais', [
            'data' => $this->data,
        ]);
    }
}
