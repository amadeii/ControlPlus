<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $uniqueSerialIndex = 'produto_unicos_produto_codigo_tipo_unique';

    public function up(): void
    {
        if (!Schema::hasTable('produto_unicos')) {
            return;
        }

        Schema::table('produto_unicos', function (Blueprint $table) {
            if (!Schema::hasColumn('produto_unicos', 'item_nfe_id')) {
                $table->unsignedBigInteger('item_nfe_id')->nullable()->after('nfce_id')->index('produto_unicos_item_nfe_id_idx');
            }
            if (!Schema::hasColumn('produto_unicos', 'item_nfce_id')) {
                $table->unsignedBigInteger('item_nfce_id')->nullable()->after('item_nfe_id')->index('produto_unicos_item_nfce_id_idx');
            }
        });

        if (!$this->indexExists('produto_unicos', $this->uniqueSerialIndex)) {
            $duplicado = DB::table('produto_unicos')
                ->select('produto_id', 'codigo', 'tipo', DB::raw('COUNT(*) as total'))
                ->groupBy('produto_id', 'codigo', 'tipo')
                ->havingRaw('COUNT(*) > 1')
                ->first();

            if ($duplicado) {
                throw new RuntimeException('Existem seriais duplicados em produto_unicos; regularize antes de criar o indice unico.');
            }

            Schema::table('produto_unicos', function (Blueprint $table) {
                $table->unique(['produto_id', 'codigo', 'tipo'], $this->uniqueSerialIndex);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('produto_unicos')) {
            return;
        }

        if ($this->indexExists('produto_unicos', $this->uniqueSerialIndex)) {
            Schema::table('produto_unicos', function (Blueprint $table) {
                $table->dropUnique($this->uniqueSerialIndex);
            });
        }

        Schema::table('produto_unicos', function (Blueprint $table) {
            if (Schema::hasColumn('produto_unicos', 'item_nfce_id')) {
                $table->dropColumn('item_nfce_id');
            }
            if (Schema::hasColumn('produto_unicos', 'item_nfe_id')) {
                $table->dropColumn('item_nfe_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index]
        );

        return !empty($result);
    }
};
