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
        // Notas fiscais (NFS-e) emitidas para a prescrição. O número controla o
        // código de integração da OS: "{prescricao_id}/{numero}".
        Schema::create('prescricao_notas_fiscais', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_id')->constrained('prescricoes')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('codigo_integracao', 60);
            $table->unsignedBigInteger('n_cod_servico')->nullable();
            $table->text('descricao_servico')->nullable();
            $table->decimal('quantidade', 10, 2)->default(1);
            $table->decimal('valor_unitario', 10, 2)->default(0);
            $table->decimal('valor_total', 10, 2)->default(0);
            $table->string('cidade_prestacao', 100)->default('Sao Paulo');
            $table->string('email_envio')->nullable();
            $table->string('status', 20)->default('rascunho');
            $table->unsignedBigInteger('n_cod_os')->nullable();
            $table->unsignedBigInteger('n_cod_nfse')->nullable();
            $table->timestamp('emitida_em')->nullable();
            $table->timestamps();

            $table->unique(['prescricao_id', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescricao_notas_fiscais');
    }
};
