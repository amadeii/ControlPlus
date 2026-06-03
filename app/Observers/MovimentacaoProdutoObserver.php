<?php

namespace App\Observers;

use App\Models\AuditEstoqueDetalhe;
use App\Models\MovimentacaoProduto;
use App\Support\AuditRequestContext;

class MovimentacaoProdutoObserver
{
    public function created(MovimentacaoProduto $m): void
    {
        try {
            $this->registrarAuditoria($m);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('audit_estoque_detalhe falhou', [
                'movimentacao_id' => $m->id ?? null,
                'erro' => $e->getMessage(),
            ]);
        }
    }

    private function registrarAuditoria(MovimentacaoProduto $m): void
    {
        if (AuditEstoqueDetalhe::where('movimentacao_produto_id', $m->id)->exists()) {
            return;
        }

        $produto = $m->produto ?: $m->produto()->first();
        if (!$produto) {
            return;
        }

        $empresaId = (int) $produto->empresa_id;
        $ctx = AuditRequestContext::capture();

        $depois = $m->estoque_atual;
        $movQtd = $m->quantidade;
        $tipo = $m->tipo;

        $antes = null;

        $depoisNumeric = $depois !== null ? (float) $depois : null;
        $movNumeric = (float) $movQtd;

        if ($depoisNumeric !== null) {
            if ($tipo === 'reducao') {
                $antes = $depoisNumeric + $movNumeric;
            } elseif ($tipo === 'incremento') {
                $antes = $depoisNumeric - $movNumeric;
            } else {
                $antes = $depoisNumeric;
            }
        }

        AuditEstoqueDetalhe::create([
            'empresa_id' => $empresaId,
            'movimentacao_produto_id' => $m->id,
            'produto_id' => $m->produto_id,
            'produto_variacao_id' => $m->produto_variacao_id,
            'deposito_id' => $m->deposito_id,
            'tipo' => $tipo ?? '',
            'tipo_transacao' => $m->tipo_transacao,
            'codigo_transacao' => $m->codigo_transacao,
            'quantidade_movimentada' => $movQtd,
            'estoque_depois' => $depois,
            'estoque_antes' => $antes,
            'user_id' => $m->user_id ?? $ctx['user_id'],
            'ip_address' => $ctx['ip_address'],
            'user_agent' => $ctx['user_agent'],
            'session_id' => $ctx['session_id'],
        ]);
    }
}
