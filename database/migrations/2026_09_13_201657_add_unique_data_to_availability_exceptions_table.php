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
        Schema::table('availability_exceptions', function (Blueprint $table) {
            // Exceções (horário especial) e bloqueios (fechado total) usam
            // esta mesma tabela, com um registro por data — a constraint única
            // evita duas linhas conflitantes para o mesmo dia (ex.: um
            // "fechado total" e um "horário especial" coexistindo).
            $table->unique('data');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('availability_exceptions', function (Blueprint $table) {
            $table->dropUnique(['data']);
        });
    }
};
