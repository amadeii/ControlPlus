<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('produto_os')) {
            return;
        }

        Schema::table('produto_os', function (Blueprint $table) {
            if (!Schema::hasColumn('produto_os', 'descricao_livre')) {
                $table->string('descricao_livre', 500)->nullable()->after('subtotal');
            }
            if (!Schema::hasColumn('produto_os', 'marca_livre')) {
                $table->string('marca_livre', 120)->nullable()->after('descricao_livre');
            }
            if (!Schema::hasColumn('produto_os', 'modelo_livre')) {
                $table->string('modelo_livre', 120)->nullable()->after('marca_livre');
            }
            if (!Schema::hasColumn('produto_os', 'imei_serial_livre')) {
                $table->string('imei_serial_livre', 160)->nullable()->after('modelo_livre');
            }
        });

        if (Schema::hasTable('ordem_servicos')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                if (!Schema::hasColumn('ordem_servicos', 'marca_equipamento')) {
                    $table->string('marca_equipamento', 120)->nullable()->after('equipamento');
                }
                if (!Schema::hasColumn('ordem_servicos', 'modelo_equipamento')) {
                    $table->string('modelo_equipamento', 120)->nullable()->after('marca_equipamento');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ordem_servicos')) {
            Schema::table('ordem_servicos', function (Blueprint $table) {
                if (Schema::hasColumn('ordem_servicos', 'modelo_equipamento')) {
                    $table->dropColumn('modelo_equipamento');
                }
                if (Schema::hasColumn('ordem_servicos', 'marca_equipamento')) {
                    $table->dropColumn('marca_equipamento');
                }
            });
        }

        if (!Schema::hasTable('produto_os')) {
            return;
        }

        Schema::table('produto_os', function (Blueprint $table) {
            foreach (['imei_serial_livre', 'modelo_livre', 'marca_livre', 'descricao_livre'] as $col) {
                if (Schema::hasColumn('produto_os', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
