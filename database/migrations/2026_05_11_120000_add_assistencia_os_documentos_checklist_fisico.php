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
                if (!Schema::hasColumn('ordem_servicos', 'senha_aparelho')) {
                    $table->string('senha_aparelho', 120)->nullable()->after('numero_serie');
                }
                if (!Schema::hasColumn('ordem_servicos', 'acessorios')) {
                    $table->text('acessorios')->nullable()->after('senha_aparelho');
                }
            });
        }

        if (!Schema::hasTable('ordem_servico_assistencia_checklist_fisico_items')) {
            Schema::create('ordem_servico_assistencia_checklist_fisico_items', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordem_servico_id');
                $table->string('item_codigo', 50);
                $table->string('titulo', 255);
                $table->string('estado', 30);
                $table->text('observacao')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->unique(['ordem_servico_id', 'item_codigo'], 'os_as_chk_fis_os_codigo_uq');
                $table->index('ordem_servico_id', 'os_as_chk_fis_os_idx');
                $table->index('user_id', 'os_as_chk_fis_user_idx');
            });
        }

        if (!Schema::hasTable('ordem_servico_anexos')) {
            Schema::create('ordem_servico_anexos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordem_servico_id');
                $table->string('tipo', 40);
                $table->string('arquivo', 255);
                $table->string('caminho', 500);
                $table->string('mime', 120)->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->index('ordem_servico_id', 'os_anexos_os_idx');
                $table->index('tipo', 'os_anexos_tipo_idx');
                $table->index('user_id', 'os_anexos_user_idx');
            });
        }

        if (!Schema::hasTable('ordem_servico_documentos')) {
            Schema::create('ordem_servico_documentos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('ordem_servico_id');
                $table->string('tipo', 30);
                $table->string('arquivo', 255);
                $table->string('caminho', 500);
                $table->timestamp('gerado_em');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();

                $table->unique(['ordem_servico_id', 'tipo'], 'os_docs_os_tipo_uq');
                $table->index('ordem_servico_id', 'os_docs_os_idx');
                $table->index('user_id', 'os_docs_user_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ordem_servico_documentos');
        Schema::dropIfExists('ordem_servico_anexos');
        Schema::dropIfExists('ordem_servico_assistencia_checklist_fisico_items');

        if (Schema::hasTable('ordem_servicos')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                if (Schema::hasColumn('ordem_servicos', 'acessorios')) {
                    $table->dropColumn('acessorios');
                }
                if (Schema::hasColumn('ordem_servicos', 'senha_aparelho')) {
                    $table->dropColumn('senha_aparelho');
                }
            });
        }
    }
};
