<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reparos_internos')) {
            return;
        }

        Schema::create('reparos_internos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedInteger('codigo_sequencial');

            $table->string('status', 30)->default('aberto');

            $table->unsignedBigInteger('tradein_inventory_item_id')->nullable();
            $table->unsignedBigInteger('produto_id')->nullable();
            $table->unsignedBigInteger('produto_unico_id')->nullable();

            $table->unsignedBigInteger('local_id')->nullable();
            $table->unsignedBigInteger('deposito_id')->nullable();

            $table->unsignedBigInteger('funcionario_id')->nullable();
            $table->text('observacao_tecnica')->nullable();

            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->unsignedBigInteger('usuario_finalizacao_id')->nullable();
            $table->unsignedBigInteger('usuario_cancelamento_id')->nullable();

            $table->timestamp('finalizado_at')->nullable();
            $table->timestamp('cancelado_at')->nullable();

            $table->timestamps();

            $table->index(['empresa_id', 'codigo_sequencial'], 'rib_empresa_cod_seq_idx');
            $table->index('empresa_id', 'rib_empresa_idx');
            $table->index('status', 'rib_status_idx');
            $table->index('tradein_inventory_item_id', 'rib_tinv_item_idx');
            $table->index('produto_id', 'rib_produto_idx');
            $table->index('produto_unico_id', 'rib_prod_unico_idx');
            $table->index('local_id', 'rib_local_idx');
            $table->index('deposito_id', 'rib_deposito_idx');
            $table->index('funcionario_id', 'rib_funcionario_idx');
            $table->index('usuario_id', 'rib_usuario_idx');
            $table->index('usuario_finalizacao_id', 'rib_usuario_fin_idx');
            $table->index('usuario_cancelamento_id', 'rib_usuario_can_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparos_internos');
    }
};
