<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Controle do envio dos anexos da prescrição para o prontuário do paciente na
 * Feegow: guarda o fileId devolvido, quando foi enviado e o erro da tentativa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescricao_anexos', function (Blueprint $table) {
            $table->string('feegow_file_id', 50)->nullable()->after('user_id');
            $table->timestamp('feegow_enviado_em')->nullable()->after('feegow_file_id');
            $table->text('feegow_erro')->nullable()->after('feegow_enviado_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prescricao_anexos', function (Blueprint $table) {
            $table->dropColumn(['feegow_file_id', 'feegow_enviado_em', 'feegow_erro']);
        });
    }
};
