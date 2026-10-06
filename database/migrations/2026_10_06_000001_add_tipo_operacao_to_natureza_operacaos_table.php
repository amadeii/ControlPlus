<?php

use App\Models\Empresa;
use App\Models\NaturezaOperacao;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'natureza_operacaos_empresa_tipo_idx';

    public function up(): void
    {
        if (!Schema::hasColumn('natureza_operacaos', 'tipo_operacao')) {
            Schema::table('natureza_operacaos', function (Blueprint $table) {
                $table->string('tipo_operacao', 10)->default('ambos')->after('movimentar_estoque');
            });
        }

        if (!$this->indexExists('natureza_operacaos', $this->indexName)) {
            Schema::table('natureza_operacaos', function (Blueprint $table) {
                $table->index(['empresa_id', 'tipo_operacao'], 'natureza_operacaos_empresa_tipo_idx');
            });
        }

        NaturezaOperacao::query()
            ->where(function ($query) {
                $query->where('descricao', 'like', '%compra%')
                    ->orWhere('descricao', 'like', '%entrada%')
                    ->orWhere('cfop_estadual', 'like', '1%')
                    ->orWhere('cfop_estadual', 'like', '2%')
                    ->orWhere('cfop_entrada_estadual', 'like', '1%')
                    ->orWhere('cfop_entrada_estadual', 'like', '2%');
            })
            ->update(['tipo_operacao' => 'entrada']);

        NaturezaOperacao::query()
            ->where('tipo_operacao', 'ambos')
            ->where(function ($query) {
                $query->where('descricao', 'like', '%venda%')
                    ->orWhere('descricao', 'like', '%saida%')
                    ->orWhere('descricao', 'like', '%saída%')
                    ->orWhere('cfop_estadual', 'like', '5%')
                    ->orWhere('cfop_estadual', 'like', '6%');
            })
            ->update(['tipo_operacao' => 'saida']);

        Empresa::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(100, function ($empresas) {
                foreach ($empresas as $empresa) {
                    $hasEntrada = NaturezaOperacao::where('empresa_id', $empresa->id)
                        ->whereIn('tipo_operacao', ['entrada', 'ambos'])
                        ->exists();

                    if ($hasEntrada) {
                        continue;
                    }

                    NaturezaOperacao::create([
                        'empresa_id' => $empresa->id,
                        'descricao' => 'Compra para comercialização',
                        'cfop_estadual' => '1102',
                        'cfop_outro_estado' => '2102',
                        'cfop_entrada_estadual' => '1102',
                        'cfop_entrada_outro_estado' => '2102',
                        'padrao' => 0,
                        'sobrescrever_cfop' => 0,
                        'movimentar_estoque' => 1,
                        'tipo_operacao' => 'entrada',
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('natureza_operacaos', 'tipo_operacao')) {
            Schema::table('natureza_operacaos', function (Blueprint $table) {
                if ($this->indexExists('natureza_operacaos', $this->indexName)) {
                    $table->dropIndex('natureza_operacaos_empresa_tipo_idx');
                }
                $table->dropColumn('tipo_operacao');
            });
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'select count(1) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $index]
        );

        return (int)($result[0]->aggregate ?? 0) > 0;
    }
};
