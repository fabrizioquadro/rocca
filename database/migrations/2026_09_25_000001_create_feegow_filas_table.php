<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fila de envio das aplicações para a Feegow.
 *
 * Mesmo padrão do sistema do instituto: a aplicação grava o registro aqui e o
 * comando `feegow:fila` (chamado pelo cron/scheduler) faz o POST na API.
 * Não usa as filas do Laravel (QUEUE_CONNECTION) — é uma fila em tabela, com
 * tentativas, backoff e o erro de cada tentativa visível.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feegow_filas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('prescricao_id')->nullable()->constrained('prescricoes')->nullOnDelete();
            $table->foreignId('prescricao_semana_id')->nullable()->constrained('prescricao_semanas')->nullOnDelete();
            $table->foreignId('prescricao_semana_atendimento_id')->nullable()
                ->constrained('prescricao_semana_atendimentos')->nullOnDelete();

            $table->string('evento', 30)->default('aplicacao');
            $table->string('situacao', 20)->default('pendente');
            $table->unsignedSmallInteger('tentativas')->default(0);
            $table->timestamp('proxima_tentativa')->nullable();
            $table->timestamp('ultima_tentativa')->nullable();
            $table->timestamp('enviado_em')->nullable();
            $table->string('agendamento_id', 50)->nullable();
            $table->text('erro')->nullable();
            $table->json('payload')->nullable();

            $table->timestamps();

            $table->index(['situacao', 'proxima_tentativa']);
            $table->index('prescricao_semana_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feegow_filas');
    }
};
