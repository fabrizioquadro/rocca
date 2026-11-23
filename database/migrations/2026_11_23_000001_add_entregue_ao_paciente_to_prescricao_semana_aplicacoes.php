<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescricao_semana_aplicacoes', function (Blueprint $table) {
            $table->boolean('entregue_ao_paciente')->default(false)->after('observacao');
        });
    }

    public function down(): void
    {
        Schema::table('prescricao_semana_aplicacoes', function (Blueprint $table) {
            $table->dropColumn('entregue_ao_paciente');
        });
    }
};
