<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Chegada do paciente na sessão. A semana zera a chegada quando o
        // paciente vai embora, então o atendimento guarda a dele para o
        // histórico (data da chegada em cada aplicação).
        Schema::table('prescricao_semana_atendimentos', function (Blueprint $table) {
            $table->dateTime('chegada_em')->nullable()->after('prescricao_semana_id');
        });

        // Atendimentos antigos não têm a chegada registrada: usa o início do
        // atendimento como data mais próxima.
        DB::table('prescricao_semana_atendimentos')
            ->whereNull('chegada_em')
            ->update(['chegada_em' => DB::raw('iniciado_em')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_semana_atendimentos', function (Blueprint $table) {
            $table->dropColumn('chegada_em');
        });
    }
};
