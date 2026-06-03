<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('acao_logs')) {
            Schema::table('acao_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('acao_logs', 'user_id')) {
                    $table->unsignedBigInteger('user_id')->nullable()->after('acao');
                    $table->index(['user_id'], 'acao_logs_user_id_index');
                }
                if (!Schema::hasColumn('acao_logs', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('user_id');
                }
                if (!Schema::hasColumn('acao_logs', 'user_agent')) {
                    $table->text('user_agent')->nullable()->after('ip_address');
                }
                if (!Schema::hasColumn('acao_logs', 'session_id')) {
                    $table->string('session_id', 191)->nullable()->after('user_agent');
                }
                if (!Schema::hasColumn('acao_logs', 'payload_json')) {
                    $table->longText('payload_json')->nullable()->after('session_id');
                }
            });

            try {
                DB::statement('ALTER TABLE acao_logs MODIFY COLUMN descricao TEXT');
            } catch (\Throwable $e) {
                // Ignore if divergent driver/state
            }
        }

        if (!Schema::hasTable('audit_estoque_detalhes')) {
            Schema::create('audit_estoque_detalhes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('movimentacao_produto_id');
                $table->unsignedBigInteger('produto_id');
                $table->unsignedBigInteger('produto_variacao_id')->nullable();
                $table->unsignedBigInteger('deposito_id')->nullable();
                $table->string('tipo', 40);
                $table->string('tipo_transacao', 80)->nullable();
                $table->string('codigo_transacao', 80)->nullable();
                $table->decimal('quantidade_movimentada', 22, 6);
                $table->decimal('estoque_depois', 22, 6);
                $table->decimal('estoque_antes', 22, 6)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id', 191)->nullable();
                $table->timestamps();

                $table->index('empresa_id', 'aesd_empresa_idx');
                $table->unique('movimentacao_produto_id', 'aesd_mov_prod_uidx');
                $table->index('produto_id', 'aesd_produto_idx');
                $table->index('produto_variacao_id', 'aesd_variacao_idx');
                $table->index('deposito_id', 'aesd_deposito_idx');
                $table->index('user_id', 'aesd_user_idx');
            });
        }

        if (!Schema::hasTable('audit_ordem_servico_alteracoes')) {
            Schema::create('audit_ordem_servico_alteracoes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id');
                $table->unsignedBigInteger('ordem_servico_id')->nullable();
                $table->string('evento', 40)->default('update');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->string('session_id', 191)->nullable();
                $table->longText('valores_antes_json')->nullable();
                $table->longText('valores_depois_json')->nullable();
                $table->longText('diff_json')->nullable();
                $table->longText('snapshot_exclusao_json')->nullable();
                $table->text('motivo_auditoria')->nullable();
                $table->timestamps();

                $table->index('empresa_id', 'aosal_empresa_idx');
                $table->index('ordem_servico_id', 'aosal_os_idx');
                $table->index('user_id', 'aosal_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_ordem_servico_alteracoes');
        Schema::dropIfExists('audit_estoque_detalhes');

        if (Schema::hasTable('acao_logs')) {
            Schema::table('acao_logs', function (Blueprint $table) {
                foreach (['user_id', 'ip_address', 'user_agent', 'session_id', 'payload_json'] as $col) {
                    if (Schema::hasColumn('acao_logs', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });

            try {
                DB::statement('ALTER TABLE acao_logs MODIFY COLUMN descricao VARCHAR(255)');
            } catch (\Throwable $e) {
            }
        }
    }
};
