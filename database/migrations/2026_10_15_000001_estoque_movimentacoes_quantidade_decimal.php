<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A quantidade movimentada no estoque passa a aceitar fração.
     *
     * Uma ampola aplicada como 0,5 (medicamento avulso ou componente de combo)
     * precisa baixar 0,5. Em coluna INT o banco arredondava o valor gravado
     * (0,5 entrava como 1) e o saldo do lote saía errado.
     */
    public function up(): void
    {
        $this->alterar('DECIMAL(10,3) NOT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->alterar('INT NOT NULL');
    }

    /**
     * Recria a coluna com o tipo informado.
     */
    private function alterar(string $tipo): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE estoque_movimentacoes MODIFY quantidade '.$tipo);
    }
};
