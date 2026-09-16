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
        Schema::table('financeiro_pagamentos', function (Blueprint $table) {
            // dinheiro, cartao_credito, cartao_debito, pix, link_pagamento
            $table->string('forma_pagamento', 30)->nullable()->after('valor');

            // Parcelas do cartão/link (1 a 12); nas demais formas fica 1
            $table->unsignedTinyInteger('parcelas')->default(1)->after('forma_pagamento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financeiro_pagamentos', function (Blueprint $table) {
            $table->dropColumn(['forma_pagamento', 'parcelas']);
        });
    }
};
