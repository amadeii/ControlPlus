<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\OrdemServico;

class OrdemServicoViewController extends Controller
{
    public function index($hash){
        $ordem = OrdemServico::with(['itens.produto', 'servicos.servico', 'cliente'])
            ->where('hash_link', $hash)->first();

        if (!$ordem) {
            abort(404);
        }

        $empresa = $ordem->empresa;

        return view('ordem_servico.link', compact('ordem', 'empresa'));
    }
}
