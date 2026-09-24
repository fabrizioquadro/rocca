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
        // Baixa de medicamento ABERTO (vasilhame em uso). O frasco já saiu do
        // estoque fechado na abertura, então o que a baixa retira é o saldo em
        // mg que ainda estava no vasilhame.
        Schema::create('baixas_vasilhames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('data');
            $table->string('observacao')->nullable();
            $table->timestamps();
        });

        Schema::create('baixas_vasilhames_itens', function (Blueprint $table) {
            $table->id();

            // Nomes encurtados: o padrão do Laravel passa do limite do MySQL
            $table->foreignId('baixa_vasilhame_id')
                ->constrained('baixas_vasilhames', 'id', 'fk_baixa_vasilhame_itens')
                ->cascadeOnDelete();
            $table->foreignId('vasilhame_aberto_id')
                ->constrained('vasilhames_abertos', 'id', 'fk_baixa_item_vasilhame')
                ->cascadeOnDelete();
            $table->foreignId('medicamento_id')
                ->nullable()
                ->constrained('medicamentos', 'id', 'fk_baixa_item_medicamento')
                ->nullOnDelete();

            $table->decimal('mg_baixa', 10, 3);
            $table->string('motivo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baixas_vasilhames_itens');
        Schema::dropIfExists('baixas_vasilhames');
    }
};
