<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('availability_rules', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['day_of_week', 'ativo']);
        });

        DB::statement(
            'ALTER TABLE availability_rules ADD CONSTRAINT chk_availability_rules_horario CHECK (hora_fim > hora_inicio)'
        );
        DB::statement(
            'ALTER TABLE availability_rules ADD CONSTRAINT chk_availability_rules_dia CHECK (day_of_week BETWEEN 0 AND 6)'
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availability_rules');
    }
};
