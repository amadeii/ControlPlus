<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reparo_interno_linha_produtos')) {
            return;
        }

        Schema::create('reparo_interno_linha_produtos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reparo_interno_id');
            $table->unsignedBigInteger('produto_id');
            $table->decimal('quantidade', 14, 4);
            $table->decimal('valor', 14, 4)->nullable();
            $table->decimal('subtotal', 14, 4)->nullable();
            $table->timestamps();

            $table->index('reparo_interno_id', 'rilp_rep_int_idx');
            $table->index('produto_id', 'rilp_produto_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparo_interno_linha_produtos');
    }
};
