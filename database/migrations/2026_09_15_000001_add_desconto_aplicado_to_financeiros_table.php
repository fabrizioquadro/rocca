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
            // Valor do desconto em R$ "congelado" no cadastro da prescrição.
            // Depois disso não muda mais, mesmo que as semanas sejam alteradas
            // (o percentual informado é só informativo).
            $table->decimal('desconto_aplicado', 10, 2)->default(0)->after('desconto_valor');
        });

        // Financeiros existentes: congela o desconto que está valendo agora
        DB::statement(
            "UPDATE financeiros
                SET desconto_aplicado = LEAST(
                    CASE
                        WHEN desconto_tipo = 'porcentagem' THEN ROUND(valor_bruto * desconto_valor / 100, 2)
                        ELSE desconto_valor
                    END,
                    valor_bruto
                )
              WHERE desconto_tipo IS NOT NULL"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financeiros', function (Blueprint $table) {
            $table->dropColumn('desconto_aplicado');
        });
    }
};
