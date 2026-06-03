<?php

namespace App\Services;

use App\Models\ReparoInterno;
use App\Models\ReparoInternoEvento;
use Illuminate\Support\Facades\Auth;

class ReparoInternoHistoricoService
{
    public static function registrar(ReparoInterno $reparo, string $tipo, string $mensagem): void
    {
        ReparoInternoEvento::create([
            'reparo_interno_id' => $reparo->id,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'user_id' => Auth::id(),
        ]);
    }
}
