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
        // Tabela de mecanismo de concorrência (não é dado de negócio): 1 linha por
        // data com pelo menos uma tentativa de agendamento, travada via
        // "SELECT ... FOR UPDATE" para serializar reservas concorrentes no mesmo dia
        // antes da checagem de sobreposição de intervalo.
        Schema::create('agenda_locks', function (Blueprint $table) {
            $table->date('data')->primary();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_locks');
    }
};
