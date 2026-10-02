<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identificador da transação informado no recebimento (NSU/autorização da
     * maquininha, ID do Pix, E2E do comprovante...), usado na conciliação.
     */
    public function up(): void
    {
        Schema::table('financeiro_pagamentos', function (Blueprint $table) {
            $table->string('identificador', 100)->nullable()->after('valor');
            $table->index('identificador');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financeiro_pagamentos', function (Blueprint $table) {
            $table->dropColumn('identificador');
        });
    }
};
