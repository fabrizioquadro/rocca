<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Medicamentos (procedimentos) que precisam existir com id fixo:
     * Bioimpedância (10000) e Coleta (10001).
     *
     * São procedimentos: tipo "procedimento" com gera_aplicacao = false (o item
     * entra na prescrição já como aplicado, sem passar pelo fluxo da enfermagem)
     * e o id de aplicação da Feegow gravado em feegow_aplicacao_id.
     *
     * @var array<int, array<string, mixed>>
     */
    private const MEDICAMENTOS = [
        10000 => [
            'nome' => 'Bioimpedância',
            'fabricante' => null,
            'tipo' => 'procedimento',
            'tamanho_vasilhame' => null,
            'grupo_id' => null,
            'status' => 'ativo',
            'ultimo_valor_pago' => 0,
            'valor_venda' => 0,
            'estoque_minimo' => 0,
            'estoque_medio' => 0,
            'gera_aplicacao' => false,
            'feegow_aplicacao_id' => 15,
        ],
        10001 => [
            'nome' => 'Coleta',
            'fabricante' => null,
            'tipo' => 'procedimento',
            'tamanho_vasilhame' => null,
            'grupo_id' => null,
            'status' => 'ativo',
            'ultimo_valor_pago' => 0,
            'valor_venda' => 0,
            'estoque_minimo' => 0,
            'estoque_medio' => 0,
            'gera_aplicacao' => false,
            'feegow_aplicacao_id' => 15,
        ],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $agora = now();

        // insertOrIgnore: se o id já existir (banco que já rodou), nada é
        // alterado nem duplicado — a migration pode rodar em qualquer ambiente.
        foreach (self::MEDICAMENTOS as $id => $dados) {
            DB::table('medicamentos')->insertOrIgnore(array_merge($dados, [
                'id' => $id,
                'created_at' => $agora,
                'updated_at' => $agora,
            ]));
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('medicamentos')->whereIn('id', array_keys(self::MEDICAMENTOS))->delete();
    }
};
