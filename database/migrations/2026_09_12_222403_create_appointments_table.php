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
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_atuacao_id')
                ->constrained('areas_atuacao')
                ->restrictOnDelete();
            $table->string('nome_cliente', 150);
            $table->string('email_cliente', 190);
            $table->string('telefone_cliente', 30);
            $table->text('observacao')->nullable();
            $table->date('data');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->enum('status', ['pending', 'confirmed', 'cancelled', 'completed', 'no_show'])
                ->default('confirmed');
            $table->string('cancelled_reason', 255)->nullable();
            $table->timestamps();

            // Coluna gerada: só assume valor (1) enquanto o agendamento está ativo
            // (pending/confirmed). Ao cancelar, vira NULL e libera o índice único
            // abaixo para outro agendamento no mesmo horário (rede de segurança
            // secundária de concorrência, além do lock por data).
            $table->unsignedTinyInteger('active_slot')
                ->nullable()
                ->virtualAs("IF(status in ('pending','confirmed'), 1, NULL)");

            $table->index('email_cliente');
            $table->index('status');
            // Suporte à checagem de sobreposição por intervalo — mecanismo
            // principal de concorrência (ver AppointmentService).
            $table->index(['data', 'status', 'hora_inicio', 'hora_fim']);
            $table->unique(['data', 'hora_inicio', 'active_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
