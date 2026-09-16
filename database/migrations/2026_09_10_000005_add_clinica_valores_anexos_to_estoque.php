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
        // Clínica que comprou os medicamentos da entrada
        Schema::table('entradas', function (Blueprint $table) {
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
        });

        // Valores do item
        Schema::table('entrada_itens', function (Blueprint $table) {
            $table->decimal('valor_unitario', 10, 2)->nullable();
        });

        // Estoque passa a ser controlado por clínica (para permitir transferências)
        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->foreignId('clinica_id')->nullable()->constrained('clinicas')->nullOnDelete();
        });

        // Anexos da entrada (nota fiscal, recibo e etc.)
        Schema::create('entrada_anexos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_id')->constrained('entradas')->cascadeOnDelete();
            $table->string('nome');
            $table->string('arquivo');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('tamanho')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entrada_anexos');

        Schema::table('estoque_movimentacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinica_id');
        });

        Schema::table('entrada_itens', function (Blueprint $table) {
            $table->dropColumn('valor_unitario');
        });

        Schema::table('entradas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('clinica_id');
        });
    }
};
