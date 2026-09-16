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
        Schema::table('financeiro_parcelas', function (Blueprint $table) {
            // Composição da parcela: bruto da semana - desconto + adicional
            $table->decimal('valor_bruto', 10, 2)->default(0)->after('prescricao_semana_id');
            $table->decimal('valor_desconto', 10, 2)->default(0)->after('valor_bruto');
            $table->decimal('valor_adicional', 10, 2)->default(0)->after('valor_desconto');
        });

        // Parcelas já cadastradas não têm ajustes: o bruto é o próprio valor
        DB::table('financeiro_parcelas')->update(['valor_bruto' => DB::raw('valor')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financeiro_parcelas', function (Blueprint $table) {
            $table->dropColumn(['valor_bruto', 'valor_desconto', 'valor_adicional']);
        });
    }
};
