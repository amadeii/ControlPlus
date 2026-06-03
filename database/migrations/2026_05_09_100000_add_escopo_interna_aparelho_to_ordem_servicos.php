<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ordem_servicos')) {
            return;
        }

        Schema::table('ordem_servicos', function (Blueprint $table) {
            if (!Schema::hasColumn('ordem_servicos', 'escopo_ordem_servico')) {
                $table->string('escopo_ordem_servico', 20)->default('cliente')->after('tradein_inventory_item_id');
                $table->index('escopo_ordem_servico', 'ordem_svc_escopo_idx');
            }
            if (!Schema::hasColumn('ordem_servicos', 'produto_aparelho_id')) {
                $table->unsignedBigInteger('produto_aparelho_id')->nullable()->after('escopo_ordem_servico');
                $table->index('produto_aparelho_id', 'ordem_svc_prod_aparelho_idx');
            }
            if (!Schema::hasColumn('ordem_servicos', 'produto_aparelho_unico_id')) {
                $table->unsignedBigInteger('produto_aparelho_unico_id')->nullable()->after('produto_aparelho_id');
                $table->index('produto_aparelho_unico_id', 'ordem_svc_prod_ap_uco_idx');
            }
        });

        DB::table('ordem_servicos')->whereNull('escopo_ordem_servico')->update(['escopo_ordem_servico' => 'cliente']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('ordem_servicos')) {
            return;
        }

        Schema::table('ordem_servicos', function (Blueprint $table) {
            if (Schema::hasColumn('ordem_servicos', 'produto_aparelho_unico_id')) {
                $table->dropColumn('produto_aparelho_unico_id');
            }
            if (Schema::hasColumn('ordem_servicos', 'produto_aparelho_id')) {
                $table->dropColumn('produto_aparelho_id');
            }
            if (Schema::hasColumn('ordem_servicos', 'escopo_ordem_servico')) {
                $table->dropColumn('escopo_ordem_servico');
            }
        });
    }
};
