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
        // Um registro por medicamento aplicado. O código de barras, o lote e o
        // vencimento ficam COPIADOS aqui: é o que foi aplicado de verdade e não
        // pode mudar se a entrada do estoque for editada depois.
        Schema::create('prescricao_semana_aplicacoes', function (Blueprint $table) {
            $table->id();
            // Nome da constraint encurtado: o padrão do Laravel passa do limite
            // de 64 caracteres do MySQL
            $table->foreignId('prescricao_semana_atendimento_id')
                ->constrained('prescricao_semana_atendimentos', 'id', 'fk_aplicacoes_atendimento')
                ->cascadeOnDelete();
            $table->foreignId('prescricao_semana_item_id')->constrained('prescricao_semana_itens')->cascadeOnDelete();
            $table->foreignId('entrada_item_id')->nullable()->constrained('entrada_itens')->nullOnDelete();
            $table->foreignId('medicamento_id')->nullable()->constrained('medicamentos')->nullOnDelete();

            $table->string('codigo_barras', 100)->nullable();
            $table->string('lote', 100)->nullable();
            $table->date('vencimento')->nullable();

            $table->decimal('quantidade', 10, 3)->nullable();
            $table->dateTime('aplicado_em');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();   // observação do medicamento
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescricao_semana_aplicacoes');
    }
};
