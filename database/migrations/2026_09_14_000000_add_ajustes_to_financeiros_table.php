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
        Schema::table('financeiros', function (Blueprint $table) {
            // Soma das semanas, antes do desconto/adicional
            $table->decimal('valor_bruto', 10, 2)->default(0)->after('clinica_id');

            // 'porcentagem' = desconto_valor é um percentual; 'valor' = é um valor em R$
            $table->string('desconto_tipo', 20)->nullable()->after('valor_bruto');
            $table->decimal('desconto_valor', 10, 2)->default(0)->after('desconto_tipo');
            $table->decimal('adicional_valor', 10, 2)->default(0)->after('desconto_valor');
        });

        // Financeiros já cadastrados não têm ajustes: o bruto é o próprio total
        DB::table('financeiros')->update(['valor_bruto' => DB::raw('valor_total')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financeiros', function (Blueprint $table) {
            $table->dropColumn(['valor_bruto', 'desconto_tipo', 'desconto_valor', 'adicional_valor']);
        });
    }
};
