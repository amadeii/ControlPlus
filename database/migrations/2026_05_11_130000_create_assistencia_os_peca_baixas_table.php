<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assistencia_os_peca_baixas')) {
            return;
        }

        Schema::create('assistencia_os_peca_baixas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('ordem_servico_id');
            $table->unsignedBigInteger('produto_os_id');
            $table->unsignedBigInteger('tradein_inventory_item_id');
            $table->string('status', 20)->default('pendente');
            $table->unsignedBigInteger('deposito_id')->nullable();
            $table->unsignedBigInteger('movimentacao_produto_id')->nullable();
            $table->unsignedBigInteger('custo_lancamento_id')->nullable();
            $table->unsignedBigInteger('aprovado_por_user_id')->nullable();
            $table->timestamp('baixado_em')->nullable();
            $table->timestamps();

            $table->index('empresa_id', 'aopb_empresa_idx');
            $table->index('ordem_servico_id', 'aopb_ordem_servico_idx');
            $table->unique('produto_os_id', 'aopb_produto_os_uidx');
            $table->index('tradein_inventory_item_id', 'aopb_tradein_item_idx');
            $table->index('status', 'aopb_status_idx');
            $table->index('deposito_id', 'aopb_deposito_idx');
            $table->index('movimentacao_produto_id', 'aopb_mov_prod_idx');
            $table->index('custo_lancamento_id', 'aopb_custo_lanc_idx');
            $table->index('aprovado_por_user_id', 'aopb_aprovado_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistencia_os_peca_baixas');
    }
};
