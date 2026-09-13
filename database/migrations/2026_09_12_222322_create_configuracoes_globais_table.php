<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('configuracoes_globais', function (Blueprint $table) {
            $table->id();
            $table->smallInteger('duracao_consulta_min')->default(60);
            $table->smallInteger('intervalo_entre_consultas_min')->default(0);
            $table->smallInteger('antecedencia_minima_horas')->default(2);
            $table->smallInteger('antecedencia_maxima_dias')->default(60);
            $table->enum('modo_confirmacao', ['automatico', 'manual'])->default('automatico');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('configuracoes_globais');
    }
};
