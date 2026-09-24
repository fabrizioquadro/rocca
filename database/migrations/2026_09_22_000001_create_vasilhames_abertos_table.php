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
        // Medicamentos do tipo miligrama são vasilhames: cada código de barras
        // é um frasco que precisa ser ABERTO para começar a ser usado. Depois
        // de aberto, o que controla o estoque dele é o saldo em mg.
        Schema::create('vasilhames_abertos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_item_id')->constrained('entrada_itens')->cascadeOnDelete();
            $table->foreignId('medicamento_id')->constrained('medicamentos');
            $table->foreignId('clinica_id')->constrained('clinicas');

            // Quanto ainda resta no vasilhame (começa no tamanho do vasilhame)
            $table->decimal('mg_restantes', 10, 3);

            $table->dateTime('aberto_em');
            $table->foreignId('aberto_por_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Preenchido quando o vasilhame zera: não pode mais ser usado
            $table->dateTime('esgotado_em')->nullable();

            $table->text('observacao')->nullable();
            $table->timestamps();

            $table->index(['entrada_item_id', 'clinica_id']);
            $table->index(['medicamento_id', 'esgotado_em']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vasilhames_abertos');
    }
};
