<?php

use App\Models\Empresa;
use App\Models\NaturezaOperacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('natureza_operacaos', 'tipo_operacao')) {
            return;
        }

        NaturezaOperacao::query()
            ->where('descricao', 'like', '%venda%')
            ->where(function ($query) {
                $query->where('cfop_estadual', 'like', '5%')
                    ->orWhere('cfop_estadual', 'like', '6%');
            })
            ->update(['tipo_operacao' => NaturezaOperacao::TIPO_SAIDA]);

        Empresa::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($empresas) {
                foreach ($empresas as $empresa) {
                    $hasCompra = NaturezaOperacao::where('empresa_id', $empresa->id)
                        ->where(function ($query) {
                            $query->where('descricao', 'like', '%compra%')
                                ->orWhere('descricao', 'like', '%entrada%');
                        })
                        ->whereIn('tipo_operacao', [
                            NaturezaOperacao::TIPO_ENTRADA,
                            NaturezaOperacao::TIPO_AMBOS,
                        ])
                        ->exists();

                    if ($hasCompra) {
                        continue;
                    }

                    NaturezaOperacao::create([
                        'empresa_id' => $empresa->id,
                        'descricao' => 'Compra para comercializacao',
                        'cfop_estadual' => '1102',
                        'cfop_outro_estado' => '2102',
                        'cfop_entrada_estadual' => '1102',
                        'cfop_entrada_outro_estado' => '2102',
                        'padrao' => 0,
                        'sobrescrever_cfop' => 0,
                        'movimentar_estoque' => 1,
                        'tipo_operacao' => NaturezaOperacao::TIPO_ENTRADA,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('natureza_operacaos', 'tipo_operacao')) {
            return;
        }

        NaturezaOperacao::where('descricao', 'Compra para comercializacao')
            ->where('cfop_estadual', '1102')
            ->where('cfop_outro_estado', '2102')
            ->where('cfop_entrada_estadual', '1102')
            ->where('cfop_entrada_outro_estado', '2102')
            ->where('tipo_operacao', NaturezaOperacao::TIPO_ENTRADA)
            ->delete();
    }
};
