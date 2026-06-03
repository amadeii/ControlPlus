<?php

namespace App\Services;

use App\Models\Garantia;
use App\Models\OrdemServico;
use Illuminate\Support\Facades\DB;

class AssistenciaOsFinalizacaoService
{
    public function __construct(
        private OrdemServicoDocumentoService $documentoService,
    ) {
    }

    public function deveGerarTermoFinal(OrdemServico $ordem, string $novoEstado): bool
    {
        if (!in_array($novoEstado, ['fz', 'rp'], true)) {
            return false;
        }

        return AssistenciaOsControleTecnicoService::integraControleParaEmpresa((int) $ordem->empresa_id);
    }

    public function finalizar(
        OrdemServico $ordem,
        string $novoEstado,
        $faturada,
        bool $gerarTermoFinal,
        ?callable $persistirFinanceiro = null,
    ): OrdemServico {
        $ordemFinal = DB::transaction(function () use ($ordem, $novoEstado, $faturada, $persistirFinanceiro) {
            $ordemBloqueada = OrdemServico::where('id', (int) $ordem->id)->lockForUpdate()->firstOrFail();
            $ordemBloqueada->estado = $novoEstado;
            $ordemBloqueada->faturada = $faturada;
            $ordemBloqueada->save();

            if ($persistirFinanceiro !== null) {
                $persistirFinanceiro($ordemBloqueada);
            }

            if ($ordemBloqueada->estado === 'fz' && $ordemBloqueada->cliente_id) {
                $this->criarGarantiasFinalizacaoSeNecessario($ordemBloqueada);
            }

            return OrdemServico::where('id', (int) $ordemBloqueada->id)->firstOrFail();
        });

        if ($gerarTermoFinal) {
            $conteudoTermoFinal = $this->documentoService->renderizar(
                $ordemFinal->fresh(),
                OrdemServicoDocumentoService::TIPO_FINAL
            );

            $this->documentoService->persistirConteudoSeNaoExiste(
                $ordemFinal,
                OrdemServicoDocumentoService::TIPO_FINAL,
                $conteudoTermoFinal
            );
        }

        return $ordemFinal;
    }

    private function criarGarantiasFinalizacaoSeNecessario(OrdemServico $ordem): void
    {
        if (!$ordem->cliente_id) {
            return;
        }

        $ordem->loadMissing(['itens.produto', 'servicos.servico']);

        foreach ($ordem->itens as $produto) {
            if (!$produto->produto_id || !$produto->produto || $produto->produto->prazo_garantia <= 0) {
                continue;
            }

            $existe = Garantia::where('ordem_servico_id', (int) $ordem->id)
                ->where('produto_id', (int) $produto->produto_id)
                ->exists();

            if ($existe) {
                continue;
            }

            Garantia::create([
                'empresa_id' => $ordem->empresa_id,
                'cliente_id' => $ordem->cliente_id,
                'produto_id' => $produto->produto_id,
                'ordem_servico_id' => $ordem->id,
                'usuario_id' => \Auth::user()->id,
                'prazo_garantia' => $produto->produto->prazo_garantia,
                'data_venda' => date('Y-m-d'),
            ]);
        }

        foreach ($ordem->servicos as $servico) {
            if (!$servico->servico || $servico->servico->prazo_garantia <= 0) {
                continue;
            }

            $existe = Garantia::where('ordem_servico_id', (int) $ordem->id)
                ->where('servico_id', (int) $servico->servico_id)
                ->exists();

            if ($existe) {
                continue;
            }

            Garantia::create([
                'empresa_id' => $ordem->empresa_id,
                'cliente_id' => $ordem->cliente_id,
                'servico_id' => $servico->servico_id,
                'ordem_servico_id' => $ordem->id,
                'usuario_id' => \Auth::user()->id,
                'prazo_garantia' => $servico->servico->prazo_garantia,
                'data_venda' => date('Y-m-d'),
            ]);
        }
    }
}
