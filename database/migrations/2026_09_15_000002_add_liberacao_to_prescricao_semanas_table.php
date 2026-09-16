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
        Schema::table('prescricao_semanas', function (Blueprint $table) {
            // Quem liberou a semana para a fila de atendimento sem a parcela
            // estar paga (autorização de um administrador)
            $table->foreignId('liberado_por_user_id')
                ->nullable()
                ->after('status')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('liberado_em')->nullable()->after('liberado_por_user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_semanas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liberado_por_user_id');
            $table->dropColumn('liberado_em');
        });
    }
};
