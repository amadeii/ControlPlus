<?php

namespace App\Services;

use App\Models\AssistenciaOsPecaBaixa;
use App\Models\OrdemServico;
use App\Models\ProdutoOs;
use Illuminate\Support\Facades\DB;

class AssistenciaOsPecaBaixaPendenteService
{
    public function criarPendente(OrdemServico $ordem, ProdutoOs $linha, ?int $depositoId): AssistenciaOsPecaBaixa
    {
        return DB::transaction(function () use ($ordem, $linha, $depositoId): AssistenciaOsPecaBaixa {
            $linha = ProdutoOs::where('id', (int) $linha->id)
                ->lockForUpdate()
                ->firstOrFail();

            $ordem = OrdemServico::where('id', (int) $ordem->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $linha->ordem_servico_id !== (int) $ordem->id) {
                throw new \Exception('Linha de peça não pertence à OS informada.');
            }

            $existente = AssistenciaOsPecaBaixa::where('produto_os_id', (int) $linha->id)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->status === AssistenciaOsPecaBaixa::STATUS_PENDENTE) {
                    return $existente;
                }

                if ($existente->status === AssistenciaOsPecaBaixa::STATUS_BAIXADO) {
                    return $existente;
                }

                if ($existente->status === AssistenciaOsPecaBaixa::STATUS_CANCELADO) {
                    throw new \DomainException('A pendência desta linha já foi cancelada e não pode ser recriada automaticamente.');
                }
            }

            return AssistenciaOsPecaBaixa::create([
                'empresa_id' => (int) $ordem->empresa_id,
                'ordem_servico_id' => (int) $ordem->id,
                'produto_os_id' => (int) $linha->id,
                'tradein_inventory_item_id' => (int) $ordem->tradein_inventory_item_id,
                'status' => AssistenciaOsPecaBaixa::STATUS_PENDENTE,
                'deposito_id' => $depositoId,
            ]);
        });
    }

    public function cancelarPendente(ProdutoOs $linha): void
    {
        DB::transaction(function () use ($linha): void {
            $linha = ProdutoOs::where('id', (int) $linha->id)
                ->lockForUpdate()
                ->firstOrFail();

            $pendencia = AssistenciaOsPecaBaixa::where('produto_os_id', (int) $linha->id)
                ->where('status', AssistenciaOsPecaBaixa::STATUS_PENDENTE)
                ->lockForUpdate()
                ->first();

            if (!$pendencia) {
                return;
            }

            $pendencia->status = AssistenciaOsPecaBaixa::STATUS_CANCELADO;
            $pendencia->save();
        });
    }

    public function pendenciaDaLinha(int $produtoOsId): ?AssistenciaOsPecaBaixa
    {
        return AssistenciaOsPecaBaixa::where('produto_os_id', $produtoOsId)->first();
    }
}
