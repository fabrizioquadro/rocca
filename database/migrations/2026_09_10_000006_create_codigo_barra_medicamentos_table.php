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
        // Controla o sequencial do código de barras de cada medicamento
        Schema::create('codigo_barra_medicamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicamento_id')->unique()->constrained('medicamentos')->cascadeOnDelete();
            $table->unsignedInteger('contador')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('codigo_barra_medicamentos');
    }
};
