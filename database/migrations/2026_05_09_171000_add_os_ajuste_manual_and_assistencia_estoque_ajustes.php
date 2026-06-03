<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('movimentacao_produtos')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE `movimentacao_produtos`
                    MODIFY COLUMN `tipo_transacao`
                    ENUM(
                        'venda_nfe','venda_nfce','compra','alteracao_estoque',
                        'tradein_entrada','transferencia_estoque',
                        'os_consumo_peca','os_estorno_peca',
                        'os_ajuste_manual',
                        'reparo_interno_consumo_peca','reparo_interno_estorno_peca'
                    ) NOT NULL");
            }
        }

        if (!Schema::hasTable('assistencia_estoque_ajustes_manuais')) {
            Schema::create('assistencia_estoque_ajustes_manuais', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('empresa_id');
                $table->unsignedBigInteger('produto_id');
                $table->unsignedBigInteger('produto_variacao_id')->nullable();
                $table->decimal('quantidade', 14, 4);
                $table->unsignedInteger('deposito_id')->nullable();
                $table->enum('motivo', ['perda', 'quebra', 'defeito', 'descarte']);
                $table->text('observacao');
                $table->integer('user_id')->nullable();
                $table->timestamps();

                $table->index('empresa_id', 'aseam_empresa_idx');
                $table->index('produto_id', 'aseam_produto_idx');
                $table->index('produto_variacao_id', 'aseam_variacao_idx');
                $table->index('deposito_id', 'aseam_deposito_idx');
                $table->index('user_id', 'aseam_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assistencia_estoque_ajustes_manuais');

        if (!Schema::hasTable('movimentacao_produtos')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::table('movimentacao_produtos')
            ->where('tipo_transacao', 'os_ajuste_manual')
            ->update(['tipo_transacao' => 'alteracao_estoque']);

        DB::statement("ALTER TABLE `movimentacao_produtos`
            MODIFY COLUMN `tipo_transacao`
            ENUM(
                'venda_nfe','venda_nfce','compra','alteracao_estoque',
                'tradein_entrada','transferencia_estoque',
                'os_consumo_peca','os_estorno_peca',
                'reparo_interno_consumo_peca','reparo_interno_estorno_peca'
            ) NOT NULL");
    }
};
