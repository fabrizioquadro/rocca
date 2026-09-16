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
        // Entradas de nota fiscal
        Schema::create('entradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fornecedor_id')->nullable()->constrained('fornecedores')->nullOnDelete();
            $table->string('numero_nota')->nullable();
            $table->date('data_entrada')->nullable();
            $table->text('observacao')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        // Itens (medicamentos) de cada entrada
        Schema::create('entrada_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_id')->constrained('entradas')->cascadeOnDelete();
            $table->foreignId('medicamento_id')->constrained('medicamentos');
            $table->string('lote')->nullable();
            $table->string('codigo_barras')->nullable()->index();
            $table->date('vencimento')->nullable();
            $table->unsignedInteger('quantidade');
            $table->timestamps();
        });

        // Livro-caixa do estoque: tudo que entra e sai dos medicamentos
        Schema::create('estoque_movimentacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medicamento_id')->constrained('medicamentos');
            $table->foreignId('entrada_item_id')->nullable()->constrained('entrada_itens')->nullOnDelete();
            $table->enum('tipo', ['entrada', 'estorno', 'baixa', 'transferencia', 'consumo', 'ajuste']);
            // Positivo = entrada no estoque | Negativo = saída do estoque
            $table->integer('quantidade');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observacao')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('estoque_movimentacoes');
        Schema::dropIfExists('entrada_itens');
        Schema::dropIfExists('entradas');
    }
};
