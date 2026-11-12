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
        // A constraint unique(['mes','dia','ano']) não protege
        // feriados anuais: o MySQL trata NULL como distinto de outro NULL num
        // índice único, então duas linhas com ano=NULL (anual) para o mesmo
        // mês/dia nunca colidiam. Normaliza NULL para 0 (sentinela seguro —
        // nenhum ano real é 0) só para fins de unicidade; "mes+dia+ano"
        // continua sendo a chave semântica real, então um feriado anual
        // (ano=NULL) e um feriado específico (ano=2026, por exemplo) para o
        // mesmo mês/dia continuam podendo coexistir — só duas linhas com
        // exatamente o mesmo mes+dia+ano (ambas anuais, ou ambas do mesmo
        // ano específico) passam a ser bloqueadas.
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['mes', 'dia', 'ano']);
            $table->integer('ano_normalizado')->virtualAs('COALESCE(ano, 0)')->after('ano');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->unique(['mes', 'dia', 'ano_normalizado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('holidays', function (Blueprint $table) {
            $table->dropUnique(['mes', 'dia', 'ano_normalizado']);
            $table->dropColumn('ano_normalizado');
        });

        Schema::table('holidays', function (Blueprint $table) {
            $table->unique(['mes', 'dia', 'ano']);
        });
    }
};
