<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ordem_servicos')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                if (!Schema::hasColumn('ordem_servicos', 'tecnico_responsavel_id')) {
                    $table->unsignedBigInteger('tecnico_responsavel_id')->nullable()->after('funcionario_id');
                }
                if (!Schema::hasColumn('ordem_servicos', 'data_previsao_entrega')) {
                    $table->timestamp('data_previsao_entrega')->nullable()->after('data_entrega');
                }
                if (!Schema::hasColumn('ordem_servicos', 'assistencia_fase_tecnica')) {
                    $table->string('assistencia_fase_tecnica', 40)->nullable();
                }
            });
        }

        if (!Schema::hasTable('ordem_servico_assistencia_eventos')) {
            Schema::create('ordem_servico_assistencia_eventos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordem_servico_id');
                $table->string('tipo', 40);
                $table->text('mensagem')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('ordem_servico_id', 'osaae_os_idx');
                $table->index('tipo', 'osaae_tipo_idx');
                $table->index('user_id', 'osaae_user_idx');
            });
        }

        if (!Schema::hasTable('ordem_servico_assistencia_checklist_items')) {
            Schema::create('ordem_servico_assistencia_checklist_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordem_servico_id');
                $table->string('item_codigo', 50);
                $table->string('titulo', 255);
                $table->boolean('feito')->default(false);
                $table->timestamp('feito_em')->nullable();
                $table->unsignedBigInteger('feito_por_user_id')->nullable();
                $table->timestamps();
                $table->unique(['ordem_servico_id', 'item_codigo'], 'os_as_chk_os_codigo_uq');
                $table->index('feito_por_user_id', 'osaaci_feito_por_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_assistencia_checklist_items');
        Schema::dropIfExists('ordem_servico_assistencia_eventos');

        if (Schema::hasTable('ordem_servicos')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                if (Schema::hasColumn('ordem_servicos', 'tecnico_responsavel_id')) {
                    $table->dropColumn('tecnico_responsavel_id');
                }
                if (Schema::hasColumn('ordem_servicos', 'data_previsao_entrega')) {
                    $table->dropColumn('data_previsao_entrega');
                }
                if (Schema::hasColumn('ordem_servicos', 'assistencia_fase_tecnica')) {
                    $table->dropColumn('assistencia_fase_tecnica');
                }
            });
        }
    }
};
