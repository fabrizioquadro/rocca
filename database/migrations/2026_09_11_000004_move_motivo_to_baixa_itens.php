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
        // O motivo passa a ser de cada medicamento baixado
        Schema::table('baixa_itens', function (Blueprint $table) {
            $table->text('motivo')->nullable();
        });

        // Leva o motivo (que era da baixa) para os itens já lançados
        foreach (DB::table('baixas')->get() as $baixa) {
            DB::table('baixa_itens')
                ->where('baixa_id', $baixa->id)
                ->update(['motivo' => $baixa->motivo]);
        }

        Schema::table('baixas', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('baixas', function (Blueprint $table) {
            $table->text('motivo')->nullable();
        });

        Schema::table('baixa_itens', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
