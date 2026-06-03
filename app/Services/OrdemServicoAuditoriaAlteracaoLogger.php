<?php

namespace App\Services;

use App\Models\AuditOrdemServicoAlteracao;
use App\Models\OrdemServico;
use App\Support\AuditRequestContext;
use Illuminate\Support\Carbon;

class OrdemServicoAuditoriaAlteracaoLogger
{
    /** @var list<string> */
    public const CAMPOS_AUDITADOS = [
        'descricao',
        'cliente_id',
        'valor',
        'data_inicio',
        'data_entrega',
        'data_previsao_entrega',
        'funcionario_id',
        'tecnico_responsavel_id',
        'assistencia_fase_tecnica',
        'forma_pagamento',
        'local_id',
        'usuario_id',
        'veiculo_id',
        'escopo_ordem_servico',
        'produto_aparelho_id',
        'produto_aparelho_unico_id',
        'tradein_inventory_item_id',
        'estado',
        'tipo_servico',
        'diagnostico_cliente',
        'diagnostico_tecnico',
        'defeito_encontrado',
        'equipamento',
        'marca_equipamento',
        'modelo_equipamento',
        'numero_serie',
        'cor',
        'adiantamento',
    ];

    /**
     * Snapshot only tracked columns that exist on the model table.
     *
     * @return array<string, mixed>
     */
    public static function snapshot(OrdemServico $os): array
    {
        $attrs = $os->getAttributes();
        $out = [];

        foreach (self::CAMPOS_AUDITADOS as $key) {
            if (!array_key_exists($key, $attrs)) {
                continue;
            }
            $out[$key] = self::normalizarValor($attrs[$key]);
        }

        return $out;
    }

    public static function normalizarValor(mixed $v): mixed
    {
        if ($v instanceof Carbon) {
            return $v->format('Y-m-d H:i:s');
        }
        if ($v === null) {
            return null;
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }

        return $v;
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private static function normalizarAtributos(array $attrs): array
    {
        $out = [];

        foreach ($attrs as $key => $value) {
            $out[$key] = self::normalizarValor($value);
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $antes
     * @param array<string, mixed> $depois
     * @return array<string, array{antes: mixed, depois: mixed}>
     */
    public static function calcularDiff(array $antes, array $depois): array
    {
        $diff = [];
        $keys = array_unique(array_merge(array_keys($antes), array_keys($depois)));

        foreach ($keys as $k) {
            $va = $antes[$k] ?? null;
            $vb = $depois[$k] ?? null;
            if ((string) ($va ?? '') !== (string) ($vb ?? '')) {
                $diff[$k] = ['antes' => $va, 'depois' => $vb];
            }
        }

        return $diff;
    }

    public static function registrarAlteracao(
        int $empresaId,
        int $ordemServicoId,
        array $antes,
        array $depois,
        array $diff
    ): void {
        if ($diff === []) {
            return;
        }

        $ctx = AuditRequestContext::capture();

        AuditOrdemServicoAlteracao::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordemServicoId,
            'evento' => 'update',
            'user_id' => $ctx['user_id'],
            'ip_address' => $ctx['ip_address'],
            'user_agent' => $ctx['user_agent'],
            'session_id' => $ctx['session_id'],
            'valores_antes_json' => $antes,
            'valores_depois_json' => $depois,
            'diff_json' => $diff,
            'snapshot_exclusao_json' => null,
            'motivo_auditoria' => null,
        ]);
    }

    /**
     * Persistir registro de exclusão **antes** da remoção física (com snapshot completo).
     *
     * @param array<string, mixed> $snapshot
     */
    public static function registrarExclusaoPlanejada(
        int $empresaId,
        int $ordemServicoId,
        string $motivo,
        array $snapshot
    ): void {
        $ctx = AuditRequestContext::capture();

        AuditOrdemServicoAlteracao::create([
            'empresa_id' => $empresaId,
            'ordem_servico_id' => $ordemServicoId,
            'evento' => 'delete',
            'user_id' => $ctx['user_id'],
            'ip_address' => $ctx['ip_address'],
            'user_agent' => $ctx['user_agent'],
            'session_id' => $ctx['session_id'],
            'valores_antes_json' => null,
            'valores_depois_json' => null,
            'diff_json' => null,
            'snapshot_exclusao_json' => $snapshot,
            'motivo_auditoria' => $motivo,
        ]);
    }

    public static function snapshotExclusao(OrdemServico $item): array
    {
        $item->loadMissing([
            'itens.produto',
            'servicos.servico',
            'cliente',
            'funcionario',
            'tecnicoResponsavel',
            'veiculo',
        ]);

        $itens = $item->itens->map(static function ($linha): array {
            return [
                'id' => $linha->id,
                'produto_id' => $linha->produto_id,
                'descricao_livre' => $linha->descricao_livre,
                'marca_livre' => $linha->marca_livre,
                'modelo_livre' => $linha->modelo_livre,
                'imei_serial_livre' => $linha->imei_serial_livre,
                'quantidade' => self::normalizarValor($linha->quantidade),
                'valor' => self::normalizarValor($linha->valor),
                'subtotal' => self::normalizarValor($linha->subtotal),
                'produto_nome' => $linha->produto?->nome,
            ];
        })->values()->all();

        $servicos = $item->servicos->map(static function ($linha): array {
            return [
                'id' => $linha->id,
                'servico_id' => $linha->servico_id,
                'status' => self::normalizarValor($linha->status),
                'quantidade' => self::normalizarValor($linha->quantidade),
                'valor' => self::normalizarValor($linha->valor),
                'subtotal' => self::normalizarValor($linha->subtotal),
                'servico_nome' => $linha->servico?->nome,
            ];
        })->values()->all();

        $base = [
            'id' => $item->id,
            'codigo_sequencial' => $item->codigo_sequencial,
            'estado' => $item->estado,
            'empresa_id' => $item->empresa_id,
            'cliente_id' => $item->cliente_id,
            'valor' => (string) $item->valor,
            'qt_itens_produto' => $item->itens->count(),
            'qt_servicos' => $item->servicos->count(),
            'assistencia_fase_tecnica' => $item->assistencia_fase_tecnica,
            'cabecalho' => self::snapshot($item),
            'ordem_servico' => self::normalizarAtributos($item->getAttributes()),
            'cliente' => $item->cliente ? self::normalizarAtributos($item->cliente->getAttributes()) : null,
            'funcionario' => $item->funcionario ? self::normalizarAtributos($item->funcionario->getAttributes()) : null,
            'tecnico_responsavel' => $item->tecnicoResponsavel ? self::normalizarAtributos($item->tecnicoResponsavel->getAttributes()) : null,
            'veiculo' => $item->veiculo ? self::normalizarAtributos($item->veiculo->getAttributes()) : null,
            'itens' => $itens,
            'servicos' => $servicos,
        ];

        return $base;
    }
}
