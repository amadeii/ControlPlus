<?php

namespace App\Services;

use App\Models\OrdemServico;
use App\Models\OrdemServicoAnexo;
use App\Models\OrdemServicoAssistenciaChecklistFisicoItem;
use App\Models\OrdemServicoAssistenciaChecklistItem;
use App\Models\OrdemServicoDocumento;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssistenciaOsAberturaService
{
    public function __construct(
        private AssistenciaOsControleTecnicoService $controleTecnicoService,
        private OrdemServicoDocumentoService $documentoService,
    ) {
    }

    public function concluirAbertura(OrdemServico $ordem, Request $request): void
    {
        $this->persistirChecklistFisicoAbertura($ordem, $request);
        $this->salvarAnexosAssistencia($ordem, $request);
        $this->controleTecnicoService->aposAbertura($ordem->fresh(['tecnicoResponsavel']));
        $this->documentoService->gerarSeNaoExiste($ordem->fresh(), OrdemServicoDocumentoService::TIPO_ENTRADA);
    }

    public function compensarFalhaAbertura(OrdemServico $ordem): void
    {
        $anexos = OrdemServicoAnexo::where('ordem_servico_id', (int) $ordem->id)->get();
        $documentos = OrdemServicoDocumento::where('ordem_servico_id', (int) $ordem->id)->get();

        foreach ($anexos as $anexo) {
            $path = public_path($anexo->caminho);
            if ($anexo->caminho && file_exists($path)) {
                @unlink($path);
            }
        }

        foreach ($documentos as $documento) {
            $path = $this->documentoService->resolverCaminhoFisico($documento);
            if ($path && file_exists($path)) {
                @unlink($path);
            }
        }

        DB::transaction(function () use ($ordem): void {
            OrdemServicoDocumento::where('ordem_servico_id', (int) $ordem->id)->delete();
            OrdemServicoAnexo::where('ordem_servico_id', (int) $ordem->id)->delete();
            OrdemServicoAssistenciaChecklistFisicoItem::where('ordem_servico_id', (int) $ordem->id)->delete();
            OrdemServicoAssistenciaChecklistItem::where('ordem_servico_id', (int) $ordem->id)->delete();
            $ordem->assistenciaEventos()->delete();
            $ordem->delete();
        });
    }

    private function persistirChecklistFisicoAbertura(OrdemServico $ordem, Request $request): void
    {
        $valores = (array) $request->input('checklist_fisico', []);
        $observacoes = (array) $request->input('checklist_fisico_observacao', []);

        foreach (OrdemServico::assistenciaChecklistFisicoDefinicoes() as $codigo => $titulo) {
            OrdemServicoAssistenciaChecklistFisicoItem::updateOrCreate(
                [
                    'ordem_servico_id' => (int) $ordem->id,
                    'item_codigo' => $codigo,
                ],
                [
                    'titulo' => $titulo,
                    'estado' => (string) ($valores[$codigo] ?? 'nao_testado'),
                    'observacao' => isset($observacoes[$codigo]) ? trim((string) $observacoes[$codigo]) : null,
                    'user_id' => Auth::id(),
                ]
            );
        }
    }

    private function salvarAnexosAssistencia(OrdemServico $ordem, Request $request): void
    {
        $fotos = $request->file('fotos', []);
        if (!is_array($fotos) || empty($fotos)) {
            return;
        }

        $dir = public_path('uploads/ordem_servico_anexos');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        foreach ($this->normalizarArquivosFotosOs($fotos) as $tipo => $arquivos) {
            foreach ($arquivos as $file) {
                if (!$file || !$file->isValid()) {
                    continue;
                }

                $ext = $file->getClientOriginalExtension();
                $arquivo = 'os_' . (int) $ordem->id . '_' . $tipo . '_' . Str::random(12) . '.' . $ext;
                $file->move($dir, $arquivo);

                OrdemServicoAnexo::create([
                    'ordem_servico_id' => (int) $ordem->id,
                    'tipo' => $tipo,
                    'arquivo' => $arquivo,
                    'caminho' => 'uploads/ordem_servico_anexos/' . $arquivo,
                    'mime' => $file->getClientMimeType(),
                    'user_id' => Auth::id(),
                ]);
            }
        }
    }

    private function normalizarArquivosFotosOs(array $fotos): array
    {
        $out = [];
        foreach (['frente', 'verso', 'laterais', 'outras'] as $tipo) {
            if (!isset($fotos[$tipo])) {
                continue;
            }

            $valor = $fotos[$tipo];
            $out[$tipo] = is_array($valor) ? $valor : [$valor];
        }

        return $out;
    }
}
