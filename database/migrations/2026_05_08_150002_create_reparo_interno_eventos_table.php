<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reparo_interno_eventos')) {
            return;
        }

        Schema::create('reparo_interno_eventos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('reparo_interno_id');
            $table->string('tipo', 40);
            $table->text('mensagem')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index('reparo_interno_id', 'rie_rep_int_idx');
            $table->index('tipo', 'rie_tipo_idx');
            $table->index('user_id', 'rie_user_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reparo_interno_eventos');
    }
};
