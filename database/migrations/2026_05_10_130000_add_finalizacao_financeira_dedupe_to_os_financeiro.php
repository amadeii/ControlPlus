<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_ordem_servicos', function (Blueprint $table) {
            if (!Schema::hasColumn('fatura_ordem_servicos', 'finalizacao_financeira_chave')) {
                $table->string('finalizacao_financeira_chave', 191)->nullable()->after('valor');
            }
            if (!Schema::hasColumn('fatura_ordem_servicos', 'finalizacao_financeira_parcela')) {
                $table->unsignedInteger('finalizacao_financeira_parcela')->nullable()->after('finalizacao_financeira_chave');
            }

            $table->unique(
                ['finalizacao_financeira_chave', 'finalizacao_financeira_parcela'],
                'fos_fin_chave_parcela_unique'
            );
        });

        Schema::table('conta_recebers', function (Blueprint $table) {
            if (!Schema::hasColumn('conta_recebers', 'finalizacao_financeira_chave')) {
                $table->string('finalizacao_financeira_chave', 191)->nullable()->after('ordem_servico_id');
            }
            if (!Schema::hasColumn('conta_recebers', 'finalizacao_financeira_parcela')) {
                $table->unsignedInteger('finalizacao_financeira_parcela')->nullable()->after('finalizacao_financeira_chave');
            }

            $table->unique(
                ['finalizacao_financeira_chave', 'finalizacao_financeira_parcela'],
                'cr_fin_chave_parcela_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('conta_recebers', function (Blueprint $table) {
            if (Schema::hasColumn('conta_recebers', 'finalizacao_financeira_chave')) {
                $table->dropUnique('cr_fin_chave_parcela_unique');
                $table->dropColumn(['finalizacao_financeira_chave', 'finalizacao_financeira_parcela']);
            }
        });

        Schema::table('fatura_ordem_servicos', function (Blueprint $table) {
            if (Schema::hasColumn('fatura_ordem_servicos', 'finalizacao_financeira_chave')) {
                $table->dropUnique('fos_fin_chave_parcela_unique');
                $table->dropColumn(['finalizacao_financeira_chave', 'finalizacao_financeira_parcela']);
            }
        });
    }
};
