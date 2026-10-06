<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $indexName = 'nves_empresa_tp_serial_status_idx';

    public function up(): void
    {
        if (!Schema::hasColumn('nves', 'serial_status')) {
            Schema::table('nves', function (Blueprint $table) {
                $table->string('serial_status', 20)->default('concluido')->after('deposito_id');
            });
        }

        if (!$this->indexExists('nves', $this->indexName)) {
            Schema::table('nves', function (Blueprint $table) {
                $table->index(['empresa_id', 'tpNF', 'serial_status'], 'nves_empresa_tp_serial_status_idx');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('nves', 'serial_status')) {
            return;
        }

        Schema::table('nves', function (Blueprint $table) {
            if ($this->indexExists('nves', $this->indexName)) {
                $table->dropIndex($this->indexName);
            }
            $table->dropColumn('serial_status');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $database = DB::getDatabaseName();
        $result = DB::select(
            'select count(1) as aggregate from information_schema.statistics where table_schema = ? and table_name = ? and index_name = ?',
            [$database, $table, $index]
        );

        return (int)($result[0]->aggregate ?? 0) > 0;
    }
};
