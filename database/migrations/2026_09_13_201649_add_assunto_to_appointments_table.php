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
        Schema::table('appointments', function (Blueprint $table) {
            // Fase 4C, item 9: assunto passa a ser obrigatório no agendamento
            // público (nome/e-mail/telefone já eram) — necessário para o
            // escritório saber do que trata a consulta e para o e-mail de
            // cancelamento administrativo fazer sentido para o cliente.
            $table->string('assunto', 150)->after('telefone_cliente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn('assunto');
        });
    }
};
