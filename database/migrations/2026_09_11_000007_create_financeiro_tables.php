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
        // Financeiro (mestre): um registro por prescrição
        Schema::create('financeiros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_id')->unique()->constrained('prescricoes')->cascadeOnDelete();
            $table->foreignId('clinica_id')->constrained('clinicas');
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->unsignedInteger('quantidade_parcelas')->default(0);
            $table->text('observacao')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();
        });

        // Parcelas: uma por semana com aplicação (vencimento = data da aplicação)
        Schema::create('financeiro_parcelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financeiro_id')->constrained('financeiros')->cascadeOnDelete();
            $table->foreignId('prescricao_semana_id')->constrained('prescricao_semanas')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->date('vencimento')->nullable();
            $table->decimal('valor', 10, 2);
            $table->enum('status', ['aberta', 'paga'])->default('aberta');
            $table->string('observacao', 255)->nullable();
            $table->timestamps();

            $table->index(['financeiro_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('financeiro_parcelas');
        Schema::dropIfExists('financeiros');
    }
};
