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
        // Pagamentos recebidos no financeiro (a alocação nas parcelas é
        // recalculada sempre que algo muda)
        Schema::create('financeiro_pagamentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financeiro_id')->constrained('financeiros')->cascadeOnDelete();
            $table->decimal('valor', 10, 2);
            $table->date('data_pagamento');
            $table->string('observacao', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['financeiro_id', 'data_pagamento']);
        });

        // Quanto cada parcela já recebeu
        Schema::table('financeiro_parcelas', function (Blueprint $table) {
            $table->decimal('valor_pago', 10, 2)->default(0)->after('valor');
        });

        // Parcela pode ficar parcialmente paga
        DB::statement("ALTER TABLE financeiro_parcelas MODIFY status ENUM('aberta','paga','parcial') NOT NULL DEFAULT 'aberta'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE financeiro_parcelas MODIFY status ENUM('aberta','paga') NOT NULL DEFAULT 'aberta'");

        Schema::table('financeiro_parcelas', function (Blueprint $table) {
            $table->dropColumn('valor_pago');
        });

        Schema::dropIfExists('financeiro_pagamentos');
    }
};
