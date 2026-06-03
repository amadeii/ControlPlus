<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tradein_inventory_item_custo_peca_os_lancamentos')) {
            return;
        }

        Schema::create('tradein_inventory_item_custo_peca_os_lancamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('tradein_inventory_item_id');
            $table->unsignedBigInteger('ordem_servico_id');
            $table->unsignedBigInteger('produto_os_id');
            $table->unsignedBigInteger('movimentacao_produto_id')->nullable();
            $table->unsignedBigInteger('produto_peca_id')->nullable();
            $table->decimal('quantidade_peca', 14, 4);
            $table->decimal('valor_compra_unitario_peca', 14, 4)->nullable();
            $table->decimal('valor_custo_incremento', 14, 4);
            $table->decimal('custo_aparelho_antes', 14, 2)->nullable();
            $table->decimal('custo_aparelho_depois', 14, 2)->nullable();
            $table->decimal('valor_avaliado_tradein_origem', 14, 2)->nullable()->comment('Valor avaliado do trade-in (Tradein.valor_avaliado) quando do lançamento');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            // Prefixos curtos (< 64 caracteres — limite MySQL para nomes de índice/constraints)
            $table->index('empresa_id', 'ticpol_empresa_idx');
            $table->index('tradein_inventory_item_id', 'ticpol_tinv_item_idx');
            $table->index('ordem_servico_id', 'ticpol_ordem_serv_idx');
            $table->unique('produto_os_id', 'ticpol_produto_os_uidx');
            $table->index('movimentacao_produto_id', 'ticpol_mov_prod_idx');
            $table->index('produto_peca_id', 'ticpol_prod_peca_idx');
            $table->index('user_id', 'ticpol_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tradein_inventory_item_custo_peca_os_lancamentos');
    }
};
