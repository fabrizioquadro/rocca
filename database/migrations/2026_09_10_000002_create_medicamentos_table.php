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
        Schema::create('medicamentos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('fabricante')->nullable();
            $table->enum('tipo', ['ampola', 'miligrama', 'procedimento']);
            $table->decimal('tamanho_vasilhame', 10, 2)->nullable();
            $table->foreignId('grupo_id')->nullable()->constrained('grupos')->nullOnDelete();
            $table->enum('status', ['ativo', 'inativo'])->default('ativo');
            $table->decimal('ultimo_valor_pago', 10, 2)->nullable();
            $table->decimal('valor_venda', 10, 2)->nullable();
            $table->unsignedInteger('estoque_minimo')->nullable();
            $table->unsignedInteger('estoque_medio')->nullable();
            $table->boolean('gera_aplicacao')->default(false);
            $table->unsignedBigInteger('feegow_aplicacao_id')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('medicamentos');
    }
};
