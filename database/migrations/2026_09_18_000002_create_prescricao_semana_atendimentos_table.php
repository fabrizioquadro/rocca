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
        // Cada vez que o paciente é atendido na clínica: a aplicação parcial
        // faz a mesma semana ter mais de um atendimento.
        Schema::create('prescricao_semana_atendimentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_semana_id')->constrained('prescricao_semanas')->cascadeOnDelete();
            $table->dateTime('iniciado_em');
            $table->foreignId('iniciado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('finalizado_em')->nullable();
            $table->foreignId('finalizado_por_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();   // observação geral do atendimento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescricao_semana_atendimentos');
    }
};
