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
        Schema::table('prescricao_notas_fiscais', function (Blueprint $table) {
            // Motivo da falha quando a Omie recusa a emissão.
            $table->text('mensagem_erro')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_notas_fiscais', function (Blueprint $table) {
            $table->dropColumn('mensagem_erro');
        });
    }
};
