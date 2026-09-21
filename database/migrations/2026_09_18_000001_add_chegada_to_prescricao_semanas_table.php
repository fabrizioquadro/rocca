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
            // Momento em que o paciente chegou (registrado quando a semana é
            // enviada para a fila de aplicação).
            $table->dateTime('chegada_em')->nullable()->after('liberado_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_semanas', function (Blueprint $table) {
            $table->dropColumn('chegada_em');
        });
    }
};
