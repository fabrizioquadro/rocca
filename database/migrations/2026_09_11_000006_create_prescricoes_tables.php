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
        // Prescrição: cabeçalho (paciente, médico, clínica, atendimento)
        Schema::create('prescricoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('paciente_id')->constrained('pacientes');
            $table->unsignedBigInteger('medico_id')->nullable();
            $table->string('medico_nome', 150)->nullable();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->enum('tipo_atendimento', ['consulta_tratamento', 'consulta_nova', 'retorno', 'coleta_bio', 'implante']);
            $table->dateTime('agendamento')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // Semanas da prescrição (numero/posicao definidos pela data prevista)
        Schema::create('prescricao_semanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_id')->constrained('prescricoes')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->date('data_prevista')->nullable();
            $table->boolean('sem_aplicacao')->default(false);
            $table->enum('status', ['agendada', 'fila_aplicacao', 'atendimento', 'aplicada', 'aplicacao_parcial'])->default('agendada');
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->index(['prescricao_id', 'numero']);
        });

        // Medicamentos/combos de cada semana
        Schema::create('prescricao_semana_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_semana_id')->constrained('prescricao_semanas')->cascadeOnDelete();
            $table->enum('tipo', ['medicamento', 'combo'])->default('medicamento');
            $table->foreignId('medicamento_id')->nullable()->constrained('medicamentos')->nullOnDelete();
            $table->foreignId('combo_id')->nullable()->constrained('combos')->nullOnDelete();
            $table->decimal('quantidade', 10, 3);
            $table->decimal('valor', 10, 2);
            $table->boolean('gera_aplicacao')->default(false);
            $table->enum('status', ['aberto', 'aplicado', 'pendente'])->default('aberto');
            $table->string('observacao', 255)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescricao_semana_itens');
        Schema::dropIfExists('prescricao_semanas');
        Schema::dropIfExists('prescricoes');
    }
};
