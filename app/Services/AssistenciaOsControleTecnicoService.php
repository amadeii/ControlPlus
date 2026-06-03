<?php

namespace App\Services;

use App\Models\ConfigGeral;
use App\Models\OrdemServico;
use App\Models\OrdemServicoAssistenciaChecklistItem;
use App\Models\OrdemServicoAssistenciaEvento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AssistenciaOsControleTecnicoService
{
    /** @return array<string, string> */
    public static function checklistDefinicoesPadrao(): array
    {
        return [
            'recebimento' => 'Equipamento recebido e conferido (acessórios / estado físico)',
            'teste_inicial' => 'Teste inicial / triagem registrada',
            'diagnostico_registrado' => 'Diagnóstico técnico preenchido',
            'orcamento_aprovacao' => 'Orçamento / autorização tratada',
            'pecas_pedido' => 'Peças pedidas ou disponíveis tratadas',
            'reparo_finalizado' => 'Reparo / substituição concluídos',
            'teste_final' => 'Teste final / controle de qualidade',
            'liberacao' => 'Equipamento liberado para entrega ao cliente',
        ];
    }

    public static function integraControleParaEmpresa(?int $empresaId): bool
    {
        if (!$empresaId) {
            return false;
        }
        $cfg = ConfigGeral::where('empresa_id', $empresaId)->first();

        return $cfg && $cfg->tipo_ordem_servico === 'assistencia técinica';
    }

    public function garantirChecklist(OrdemServico $ordem): void
    {
        if (!self::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            return;
        }

        if ($ordem->assistenciaChecklistItens()->exists()) {
            return;
        }

        DB::transaction(function () use ($ordem) {
            foreach (self::checklistDefinicoesPadrao() as $codigo => $titulo) {
                OrdemServicoAssistenciaChecklistItem::create([
                    'ordem_servico_id' => $ordem->id,
                    'item_codigo' => $codigo,
                    'titulo' => $titulo,
                    'feito' => false,
                ]);
            }
        });
    }

    public function registrarEvento(OrdemServico $ordem, string $tipo, string $mensagem): void
    {
        if (!self::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            return;
        }

        OrdemServicoAssistenciaEvento::create([
            'ordem_servico_id' => $ordem->id,
            'tipo' => substr($tipo, 0, 40),
            'mensagem' => $mensagem,
            'user_id' => Auth::id(),
        ]);
    }

    public function aposAbertura(OrdemServico $ordem): void
    {
        if (!self::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            return;
        }

        if (!$ordem->assistencia_fase_tecnica) {
            $ordem->assistencia_fase_tecnica = 'fila';
            $ordem->save();
        }

        $this->garantirChecklist($ordem);
        $ordem->loadMissing('tecnicoResponsavel');
        $detalhe = 'OS aberta para assistência. Fase inicial: '
            . (OrdemServico::assistenciaFasesTecnicas()[$ordem->assistencia_fase_tecnica] ?? $ordem->assistencia_fase_tecnica ?? 'fila')
            . '.';
        if ($ordem->tecnicoResponsavel) {
            $detalhe .= ' Técnico responsável: ' . $ordem->tecnicoResponsavel->nome . '.';
        }

        $this->registrarEvento($ordem, 'abertura', $detalhe);
    }

    /**
     * @return array<int, array{kind:string, quando:\Carbon\Carbon|string|null, titulo:string, detalhe:string}>
     */
    public function montarTimeline(OrdemServico $ordem): array
    {
        if (!self::integraControleParaEmpresa((int) $ordem->empresa_id)) {
            return [];
        }

        $rows = [];

        foreach ($ordem->assistenciaEventos()->with('user')->orderBy('id')->get() as $ev) {
            $nome = $ev->user ? $ev->user->name : 'Sistema';
            $labelTipo = [
                'abertura' => 'Abertura',
                'fase' => 'Fase operacional',
                'tecnico' => 'Técnico responsável',
                'previsao_entrega' => 'Previsão de entrega',
                'observacao' => 'Observação',
                'checklist' => 'Checklist',
                'estado' => 'Estado da OS',
            ][$ev->tipo] ?? $ev->tipo;

            $rows[] = [
                'kind' => $ev->tipo,
                'quando' => $ev->created_at,
                'titulo' => $labelTipo . ' — ' . $nome,
                'detalhe' => (string) ($ev->mensagem ?? ''),
            ];
        }

        if (\count($rows) === 0) {
            $rows[] = [
                'kind' => 'legacy',
                'quando' => $ordem->created_at,
                'titulo' => 'Cadastro da OS',
                'detalhe' => 'OS #' . $ordem->codigo_sequencial . ' criada antes do histórico operacional.',
            ];
        }

        return $rows;
    }
}
