<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fatura_nfces', function (Blueprint $table) {
            if (!Schema::hasColumn('fatura_nfces', 'parcela_numero')) {
                $table->unsignedInteger('parcela_numero')->nullable()->after('valor');
            }

            if (!Schema::hasColumn('fatura_nfces', 'total_parcelas')) {
                $table->unsignedInteger('total_parcelas')->nullable()->after('parcela_numero');
            }

            if (!Schema::hasColumn('fatura_nfces', 'bandeira_cartao')) {
                $table->string('bandeira_cartao', 2)->nullable()->after('total_parcelas');
            }

            if (!Schema::hasColumn('fatura_nfces', 'cnpj_cartao')) {
                $table->string('cnpj_cartao', 18)->nullable()->after('bandeira_cartao');
            }

            if (!Schema::hasColumn('fatura_nfces', 'cAut_cartao')) {
                $table->string('cAut_cartao', 18)->nullable()->after('cnpj_cartao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fatura_nfces', function (Blueprint $table) {
            $columns = [];
            foreach (['parcela_numero', 'total_parcelas', 'bandeira_cartao', 'cnpj_cartao', 'cAut_cartao'] as $column) {
                if (Schema::hasColumn('fatura_nfces', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
