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
        // Baixas de estoque (perda, avaria, quebra, vencimento e etc.)
        Schema::create('baixas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('motivo');
            $table->date('data')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('baixa_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('baixa_id')->constrained('baixas')->cascadeOnDelete();
            $table->foreignId('entrada_item_id')->constrained('entrada_itens');
            $table->foreignId('medicamento_id')->constrained('medicamentos');
            $table->unsignedInteger('quantidade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('baixa_itens');
        Schema::dropIfExists('baixas');
    }
};
