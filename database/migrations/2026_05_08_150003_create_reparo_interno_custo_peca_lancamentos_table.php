<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reparo_interno_custo_peca_lancamentos')) {
            return;
        }

        Schema::create('reparo_interno_custo_peca_lancamentos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('reparo_interno_id');
            $table->unsignedBigInteger('reparo_interno_linha_produto_id');
            $table->unsignedBigInteger('tradein_inventory_item_id')->nullable();
            $table->unsignedBigInteger('produto_dispositivo_id')->nullable();
            $table->unsignedBigInteger('movimentacao_produto_id')->nullable();
            $table->unsignedBigInteger('produto_peca_id')->nullable();
            $table->decimal('quantidade_peca', 14, 4);
            $table->decimal('valor_compra_unitario_peca', 14, 4)->nullable();
            $table->decimal('valor_custo_incremento', 14, 4);
            $table->decimal('custo_aparelho_antes', 14, 2)->nullable();
            $table->decimal('custo_aparelho_depois', 14, 2)->nullable();
            $table->decimal('valor_avaliado_tradein_origem', 14, 2)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            // Nomes de índice explícitos (Laravel geraria nomes > 64 chars no MySQL 5.7/8)
            $table->index('empresa_id', 'ricpl_empresa_idx');
            $table->index('reparo_interno_id', 'ricpl_rep_int_idx');
            $table->unique('reparo_interno_linha_produto_id', 'ricpl_rep_linha_uidx');
            $table->index('tradein_inventory_item_id', 'ricpl_tinv_item_idx');
            $table->index('produto_dispositivo_id', 'ricpl_prod_dev_idx');
            $table->index('movimentacao_produto_id', 'ricpl_mov_prod_idx');
            $table->index('produto_peca_id', 'ricpl_prod_peca_idx');
            $table->index('user_id', 'ricpl_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparo_interno_custo_peca_lancamentos');
    }
};
