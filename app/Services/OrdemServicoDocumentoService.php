<?php

namespace App\Services;

use App\Models\ConfigGeral;
use App\Models\Empresa;
use App\Models\OrdemServico;
use App\Models\OrdemServicoDocumento;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OrdemServicoDocumentoService
{
    public const TIPO_ENTRADA = 'entrada';

    public const TIPO_FINAL = 'final';

    public function existe(OrdemServico $ordem, string $tipo): bool
    {
        return OrdemServicoDocumento::where('ordem_servico_id', (int) $ordem->id)
            ->where('tipo', $tipo)
            ->exists();
    }

    public function gerarSeNaoExiste(OrdemServico $ordem, string $tipo, ?string $estadoDocumento = null): OrdemServicoDocumento
    {
        $existente = OrdemServicoDocumento::where('ordem_servico_id', (int) $ordem->id)
            ->where('tipo', $tipo)
            ->first();

        if ($existente) {
            return $existente;
        }

        return $this->persistirConteudo($ordem, $tipo, $this->renderizar($ordem, $tipo, $estadoDocumento));
    }

    public function renderizar(OrdemServico $ordem, string $tipo, ?string $estadoDocumento = null): string
    {
        $ordemDocumento = $ordem->withoutRelations();
        if ($estadoDocumento !== null) {
            $ordemDocumento->estado = $estadoDocumento;
        }
        if ($tipo !== self::TIPO_ENTRADA) {
            $ordemDocumento->senha_aparelho = null;
        }

        $ordemDocumento->loadMissing([
            'cliente.cidade',
            'itens.produto',
            'servicos.servico',
            'assistenciaChecklistFisicoItens',
            'anexos',
        ]);

        $config = Empresa::where('id', (int) $ordemDocumento->empresa_id)->firstOrFail();
        $configGeral = ConfigGeral::where('empresa_id', (int) $ordemDocumento->empresa_id)->first();
        $tipoDocumento = $tipo;
        $dataFinalizacaoDocumento = $tipo === self::TIPO_FINAL ? now() : null;

        $html = view('ordem_servico.imprimir', compact(
            'config',
            'ordemDocumento',
            'configGeral',
            'tipoDocumento',
            'dataFinalizacaoDocumento',
        ))->with('ordem', $ordemDocumento)->render();

        $domPdf = new Dompdf(['enable_remote' => true]);
        $domPdf->loadHtml($html);
        $domPdf->setPaper('A4');
        $domPdf->render();

        return $domPdf->output();
    }

    public function persistirConteudoSeNaoExiste(OrdemServico $ordem, string $tipo, ?string $conteudoPdf): OrdemServicoDocumento
    {
        return DB::transaction(function () use ($ordem, $tipo, $conteudoPdf) {
            $existente = OrdemServicoDocumento::where('ordem_servico_id', (int) $ordem->id)
                ->where('tipo', $tipo)
                ->lockForUpdate()
                ->first();

            if ($existente) {
                return $existente;
            }

            if ($conteudoPdf === null) {
                throw new \Exception('Conteúdo do PDF não foi preparado.');
            }

            return $this->persistirConteudo($ordem, $tipo, $conteudoPdf);
        });
    }

    private function persistirConteudo(OrdemServico $ordem, string $tipo, string $conteudoPdf): OrdemServicoDocumento
    {
        $dir = storage_path('app/ordem_servico_documentos');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $arquivo = 'os_' . (int) $ordem->id . '_' . $tipo . '_' . Str::random(12) . '.pdf';
        $caminhoRelativo = 'ordem_servico_documentos/' . $arquivo;
        file_put_contents($dir . DIRECTORY_SEPARATOR . $arquivo, $conteudoPdf);

        return OrdemServicoDocumento::create([
            'ordem_servico_id' => (int) $ordem->id,
            'tipo' => $tipo,
            'arquivo' => $arquivo,
            'caminho' => $caminhoRelativo,
            'gerado_em' => now(),
            'user_id' => Auth::id(),
        ]);
    }

    public function resolverCaminhoFisico(OrdemServicoDocumento $documento): ?string
    {
        $caminhoStorage = storage_path('app/' . ltrim((string) $documento->caminho, '/'));
        if ($documento->caminho && file_exists($caminhoStorage)) {
            return $caminhoStorage;
        }

        $caminhoPublico = public_path(ltrim((string) $documento->caminho, '/'));
        if ($documento->caminho && file_exists($caminhoPublico)) {
            return $caminhoPublico;
        }

        return null;
    }
}
