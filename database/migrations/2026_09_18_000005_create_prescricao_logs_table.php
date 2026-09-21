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
        // Histórico de tudo o que acontece na prescrição: quem fez, o que fez,
        // quando e o que mudou (dados em JSON).
        Schema::create('prescricao_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescricao_id')->constrained('prescricoes')->cascadeOnDelete();
            $table->foreignId('prescricao_semana_id')->nullable()->constrained('prescricao_semanas')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('acao', 40);
            $table->text('descricao');
            $table->json('dados')->nullable();
            $table->timestamps();

            $table->index(['prescricao_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prescricao_logs');
    }
};
