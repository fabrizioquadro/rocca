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
        Schema::table('pacientes', function (Blueprint $table) {
            // Código do cliente na Omie (tomador da NFS-e). Fica nulo enquanto
            // o paciente não for integrado na aba Fiscal da prescrição.
            $table->unsignedBigInteger('codigo_cliente_omie')->nullable()->after('observacao');
            $table->timestamp('omie_integrado_em')->nullable()->after('codigo_cliente_omie');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pacientes', function (Blueprint $table) {
            $table->dropColumn(['codigo_cliente_omie', 'omie_integrado_em']);
        });
    }
};
