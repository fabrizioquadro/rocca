<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tipos possíveis da movimentação de estoque.
     *
     * @var array<int, string>
     */
    private array $tipos = ['entrada', 'estorno', 'baixa', 'transferencia', 'consumo', 'ajuste', 'abertura'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "abertura" = o vasilhame saiu do estoque fechado e passou a ser
        // controlado em mg (tabela vasilhames_abertos).
        $this->alterarTipo($this->tipos);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('estoque_movimentacoes')->where('tipo', 'abertura')->delete();

        $this->alterarTipo(array_values(array_diff($this->tipos, ['abertura'])));
    }

    /**
     * Recria o ENUM do MySQL com a lista de tipos informada.
     *
     * @param  array<int, string>  $tipos
     */
    private function alterarTipo(array $tipos): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        $lista = collect($tipos)->map(fn (string $tipo) => "'".$tipo."'")->implode(', ');

        DB::statement('ALTER TABLE estoque_movimentacoes MODIFY tipo ENUM('.$lista.') NOT NULL');
    }
};
