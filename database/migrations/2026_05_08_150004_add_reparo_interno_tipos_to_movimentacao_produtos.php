<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('movimentacao_produtos')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE `movimentacao_produtos`
            MODIFY COLUMN `tipo_transacao`
            ENUM(
                'venda_nfe','venda_nfce','compra','alteracao_estoque',
                'tradein_entrada','transferencia_estoque',
                'os_consumo_peca','os_estorno_peca',
                'reparo_interno_consumo_peca','reparo_interno_estorno_peca'
            ) NOT NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('movimentacao_produtos')) {
            return;
        }

        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::table('movimentacao_produtos')
            ->whereIn('tipo_transacao', ['reparo_interno_consumo_peca', 'reparo_interno_estorno_peca'])
            ->update(['tipo_transacao' => 'alteracao_estoque']);

        DB::statement("ALTER TABLE `movimentacao_produtos`
            MODIFY COLUMN `tipo_transacao`
            ENUM(
                'venda_nfe','venda_nfce','compra','alteracao_estoque',
                'tradein_entrada','transferencia_estoque',
                'os_consumo_peca','os_estorno_peca'
            ) NOT NULL");
    }
};
