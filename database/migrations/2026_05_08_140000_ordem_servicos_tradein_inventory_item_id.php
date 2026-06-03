<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ordem_servicos')) {
            return;
        }

        Schema::table('ordem_servicos', function (Blueprint $table) {
            if (!Schema::hasColumn('ordem_servicos', 'tradein_inventory_item_id')) {
                $table->unsignedBigInteger('tradein_inventory_item_id')
                    ->nullable()
                    ->after('local_id');

                $table->index('tradein_inventory_item_id', 'ordem_svc_tinv_item_idx');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ordem_servicos')) {
            return;
        }

        Schema::table('ordem_servicos', function (Blueprint $table) {
            if (Schema::hasColumn('ordem_servicos', 'tradein_inventory_item_id')) {
                $table->dropColumn('tradein_inventory_item_id');
            }
        });
    }
};
