<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bioimpedância (10000) e Coleta (10001) SÃO aplicados: passam pelo fluxo da
     * enfermagem (viram item pendente da semana) como os medicamentos — o item
     * da semana é criado com gera_aplicacao = true, que é o campo lido em
     * PrescricaoSemanaService::prepararItens().
     *
     * @var array<int, int>
     */
    private const IDS = [10000, 10001];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('medicamentos')
            ->whereIn('id', self::IDS)
            ->update([
                'gera_aplicacao' => true,
                'updated_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('medicamentos')
            ->whereIn('id', self::IDS)
            ->update([
                'gera_aplicacao' => false,
                'updated_at' => now(),
            ]);
    }
};
