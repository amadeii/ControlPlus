<?php

namespace App\Services;

use App\Models\AssistenciaEstoqueAjusteManual;
use App\Models\Deposito;
use App\Models\Localizacao;
use App\Models\MovimentacaoProduto;
use App\Models\Produto;
use App\Models\ProdutoVariacao;
use App\Utils\EstoqueUtil;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AssistenciaEstoqueAjusteManualService
{
    public function __construct(private EstoqueUtil $estoqueUtil)
    {
    }

    /**
     * @throws \Throwable
     */
    public function registrar(
        int $empresaId,
        int $userId,
        int $produtoId,
        $quantidade,
        string $motivo,
        string $observacao,
        ?int $depositoIdSolicitado,
        ?int $localIdSolicitado,
        ?int $produtoVariacaoId = null,
        string $idempotencyKey = ''
    ): AssistenciaEstoqueAjusteManual {
        $motivos = array_keys(AssistenciaEstoqueAjusteManual::motivosLabels());
        if (!in_array($motivo, $motivos, true)) {
            throw new \Exception('Motivo inválido.');
        }

        $observacao = trim($observacao);
        if ($observacao === '') {
            throw new \Exception('A observação é obrigatória.');
        }

        $produto = Produto::where('empresa_id', $empresaId)->find($produtoId);
        if (!$produto) {
            throw new \Exception('Produto não encontrado para esta empresa.');
        }
        if (!$produto->gerenciar_estoque) {
            throw new \Exception('Este produto não gerencia estoque.');
        }
        if ($produto->tipo_unico) {
            throw new \Exception('Produtos serializados (tipo único) não podem ser baixados por este fluxo; use o fluxo adequado ao serial.');
        }
        if ($produtoVariacaoId) {
            $variacao = ProdutoVariacao::where('id', $produtoVariacaoId)
                ->where('produto_id', (int) $produto->id)
                ->first();
            if (!$variacao) {
                throw new \Exception('Variação de produto inválida para o item informado.');
            }
        }
        $idempotencyKey = trim($idempotencyKey);
        if ($idempotencyKey === '') {
            throw new \Exception('Chave de idempotência inválida para o ajuste manual.');
        }

        $locaisAtivosUsuarioIds = $this->resolveLocaisAtivosUsuarioIds($empresaId);
        [$localId, $depositoId] = $this->resolveContextoDepositoLocal(
            $empresaId,
            $depositoIdSolicitado,
            $localIdSolicitado,
            $locaisAtivosUsuarioIds
        );

        $cacheKey = sprintf(
            'assistencia_estoque_ajuste_manual:%d:%d:%s',
            $empresaId,
            $userId,
            $idempotencyKey
        );
        if (!Cache::add($cacheKey, 'processing', now()->addMinutes(30))) {
            throw new \Exception('Esta solicitação de ajuste já foi processada. Recarregue a tela antes de tentar novamente.');
        }

        try {
            $ajuste = DB::transaction(function () use (
                $empresaId,
                $userId,
                $produto,
                $quantidade,
                $motivo,
                $observacao,
                $depositoId,
                $localId,
                $produtoVariacaoId
            ) {
                Produto::where('id', (int) $produto->id)
                    ->where('empresa_id', $empresaId)
                    ->lockForUpdate()
                    ->first();

                $ajuste = AssistenciaEstoqueAjusteManual::create([
                    'empresa_id' => $empresaId,
                    'produto_id' => (int) $produto->id,
                    'produto_variacao_id' => $produtoVariacaoId,
                    'quantidade' => $quantidade,
                    'deposito_id' => $depositoId,
                    'motivo' => $motivo,
                    'observacao' => $observacao,
                    'user_id' => $userId,
                ]);

                $this->estoqueUtil->reduzEstoque(
                    (int) $produto->id,
                    $quantidade,
                    $produtoVariacaoId,
                    (int) $localId,
                    $depositoId
                );

                $movimentoExiste = MovimentacaoProduto::where('tipo_transacao', AssistenciaEstoqueAjusteManual::TIPO_MOVIMENTACAO)
                    ->where('codigo_transacao', (int) $ajuste->id)
                    ->lockForUpdate()
                    ->exists();

                if (!$movimentoExiste) {
                    $this->estoqueUtil->movimentacaoProduto(
                        (int) $produto->id,
                        $quantidade,
                        'reducao',
                        (int) $ajuste->id,
                        AssistenciaEstoqueAjusteManual::TIPO_MOVIMENTACAO,
                        $userId,
                        $produtoVariacaoId,
                        (int) $localId,
                        $depositoId
                    );
                }

                __createLog(
                    $empresaId,
                    'Assistência / estoque',
                    'cadastrar',
                    '[os_ajuste_manual] #' . $ajuste->id . ' — ' . $produto->nome . ' qtd ' . $quantidade . ' (' . $motivo . ')'
                );

                return $ajuste;
            });

            Cache::put($cacheKey, 'done', now()->addDay());

            return $ajuste;
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }
    }

    private function resolveLocaisAtivosUsuarioIds(int $empresaId): Collection
    {
        $locaisIds = collect();
        if (function_exists('__getLocaisAtivoUsuario')) {
            $locaisIds = __getLocaisAtivoUsuario()
                ->filter(function ($local) use ($empresaId) {
                    return isset($local->id, $local->empresa_id)
                        && (int) $local->empresa_id === $empresaId;
                })
                ->pluck('id')
                ->map(function ($id) {
                    return (int) $id;
                });
        }

        return $locaisIds
            ->filter(function ($id) {
                return $id > 0;
            })
            ->unique()
            ->values();
    }

    /**
     * @return array{0:int,1:?int}
     */
    private function resolveContextoDepositoLocal(
        int $empresaId,
        ?int $depositoIdSolicitado,
        ?int $localIdSolicitado,
        Collection $locaisAtivosUsuarioIds
    ): array {
        $localId = null;
        $depositoId = $depositoIdSolicitado;

        $localSolicitado = null;
        if ($localIdSolicitado) {
            $localSolicitado = Localizacao::where('id', $localIdSolicitado)
                ->where('empresa_id', $empresaId)
                ->where('status', 1)
                ->first();
            if (!$localSolicitado) {
                throw new \Exception('Local inválido para esta empresa.');
            }

            if ($locaisAtivosUsuarioIds->isNotEmpty() && !$locaisAtivosUsuarioIds->contains((int) $localSolicitado->id)) {
                throw new \Exception('Local inválido para o usuário logado.');
            }

            $localId = (int) $localSolicitado->id;
        }

        if ($depositoIdSolicitado) {
            $deposito = Deposito::where('id', $depositoIdSolicitado)
                ->where('empresa_id', $empresaId)
                ->where('ativo', true)
                ->first();
            if (!$deposito) {
                throw new \Exception('Depósito inválido para esta empresa.');
            }

            $localIdDeposito = (int) $deposito->local_id;
            if ($localSolicitado && (int) $localSolicitado->id !== $localIdDeposito) {
                throw new \Exception('Depósito incompatível com o local informado.');
            }

            if ($locaisAtivosUsuarioIds->isNotEmpty() && !$locaisAtivosUsuarioIds->contains($localIdDeposito)) {
                throw new \Exception('Depósito inválido para os locais ativos do usuário.');
            }

            $localId = $localIdDeposito;
        }

        if (!$localId && function_exists('__getLocalAtivo')) {
            $localAtivo = __getLocalAtivo();
            $localAtivoId = (is_object($localAtivo) && isset($localAtivo->id))
                ? (int) $localAtivo->id
                : null;
            if ($localAtivoId) {
                $localValido = Localizacao::where('id', $localAtivoId)
                    ->where('empresa_id', $empresaId)
                    ->where('status', 1)
                    ->first();
                if ($localValido) {
                    $localId = (int) $localValido->id;
                }
            }
        }

        if (!$localId) {
            throw new \Exception('Defina o local ou o depósito para registrar a baixa de estoque.');
        }

        if ($locaisAtivosUsuarioIds->isNotEmpty() && !$locaisAtivosUsuarioIds->contains((int) $localId)) {
            throw new \Exception('Local de estoque fora dos locais ativos do usuário.');
        }

        return [(int) $localId, $depositoId];
    }

}
