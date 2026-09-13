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
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->string('nome', 120);
            $table->unsignedTinyInteger('mes');
            $table->unsignedTinyInteger('dia');
            $table->smallInteger('ano')->nullable();
            $table->boolean('recorrente_anual')->default(true);
            $table->boolean('bloqueado')->default(true);
            $table->timestamps();

            $table->unique(['mes', 'dia', 'ano']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
    }
};
