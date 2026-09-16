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
        // Transferências de estoque entre clínicas
        Schema::create('transferencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clinica_id')->constrained('clinicas');              // origem
            $table->foreignId('clinica_destino_id')->constrained('clinicas');      // destino
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('observacao')->nullable();
            $table->date('data')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('transferencia_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transferencia_id')->constrained('transferencias')->cascadeOnDelete();
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
        Schema::dropIfExists('transferencia_itens');
        Schema::dropIfExists('transferencias');
    }
};
