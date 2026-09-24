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
        // De qual vasilhame aberto saiu cada aplicação (medicamentos miligrama)
        Schema::table('prescricao_semana_aplicacoes', function (Blueprint $table) {
            $table->foreignId('vasilhame_aberto_id')
                ->nullable()
                ->after('entrada_item_id')
                ->constrained('vasilhames_abertos', 'id', 'fk_aplicacoes_vasilhame')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_semana_aplicacoes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vasilhame_aberto_id');
        });
    }
};
