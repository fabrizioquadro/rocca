<?php

namespace Tests\Unit;

use App\Models\EstoqueMovimentacao;
use Tests\TestCase;

/**
 * O estoque aceita quantidade fracionada: ampola aplicada como 0,5 precisa
 * baixar 0,5. Com o cast inteiro antigo a movimentação de -0,5 virava 0.
 */
class EstoqueQuantidadeFracionadaTest extends TestCase
{
    public function test_movimentacao_guarda_a_fracao_da_ampola(): void
    {
        $movimentacao = new EstoqueMovimentacao(['quantidade' => -0.5]);

        $this->assertSame('-0.500', $movimentacao->quantidade);
        $this->assertSame('-0,5', $movimentacao->quantidade_com_sinal);
    }

    public function test_combo_com_componente_de_meia_ampola_nao_vira_inteiro(): void
    {
        $movimentacao = new EstoqueMovimentacao(['quantidade' => -3.5]);

        $this->assertSame('-3.500', $movimentacao->quantidade);
        $this->assertSame('-3,5', $movimentacao->quantidade_com_sinal);
    }

    public function test_movimentacao_inteira_continua_igual(): void
    {
        $movimentacao = new EstoqueMovimentacao(['quantidade' => -1]);

        $this->assertSame('-1.000', $movimentacao->quantidade);
        $this->assertSame('-1', $movimentacao->quantidade_com_sinal);
    }
}
