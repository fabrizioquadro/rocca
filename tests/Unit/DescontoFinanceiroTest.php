<?php

namespace Tests\Unit;

use App\Models\Financeiro;
use App\Services\PrescricaoSemanaService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Regra do desconto do financeiro: a clínica aprova cortesia (desconto que
 * zera o total), então o desconto vale o que tem para descontar e o total
 * nunca fica negativo. Só continuam recusados o valor negativo e a
 * porcentagem acima de 100%.
 */
class DescontoFinanceiroTest extends TestCase
{
    /**
     * Financeiro em memória. `$aplicado` é o desconto CONGELADO no cadastro
     * (em R$): é ele que vale, mesmo que o valor das semanas mude depois.
     */
    private function financeiro(float $bruto, float $desconto, float $adicional = 0, string $tipo = 'valor', ?float $aplicado = null): Financeiro
    {
        return new Financeiro([
            'valor_bruto' => $bruto,
            'desconto_tipo' => $tipo,
            'desconto_valor' => $desconto,
            'desconto_aplicado' => $aplicado ?? ($tipo === 'valor' ? min($desconto, $bruto) : 0),
            'adicional_valor' => $adicional,
        ]);
    }

    private function valorFinal(Financeiro $financeiro): float
    {
        return app(PrescricaoSemanaService::class)->valorFinal($financeiro);
    }

    public function test_desconto_menor_que_o_bruto_desconta_normalmente(): void
    {
        $this->assertSame(400.0, $this->valorFinal($this->financeiro(1000, 600)));
    }

    public function test_desconto_igual_ao_bruto_zera_o_total(): void
    {
        $this->assertSame(0.0, $this->valorFinal($this->financeiro(1000, 1000)));
    }

    public function test_desconto_maior_que_o_bruto_zera_o_total_sem_ficar_negativo(): void
    {
        // Desconto congelado quando as semanas valiam mais: o excedente é ignorado
        $this->assertSame(0.0, $this->valorFinal($this->financeiro(1000, 1500, 0, 'valor', 1500)));
    }

    public function test_desconto_que_zera_o_total_com_adicional_devolve_o_adicional(): void
    {
        $this->assertSame(50.0, $this->valorFinal($this->financeiro(1000, 1000, 50)));
    }

    public function test_desconto_em_porcentagem_de_100_zera_o_total(): void
    {
        $this->assertSame(0.0, $this->valorFinal($this->financeiro(1000, 100, 0, 'porcentagem')));
    }

    public function test_sem_bruto_o_total_e_zero(): void
    {
        $this->assertSame(0.0, $this->valorFinal($this->financeiro(0, 0)));
    }

    public function test_desconto_negativo_e_tratado_como_zero(): void
    {
        // Negativo é erro de digitação: não vira acréscimo nem quebra a tela
        $this->assertSame(1000.0, $this->valorFinal($this->financeiro(1000, -10)));
    }

    public function test_adicional_negativo_continua_recusado(): void
    {
        $this->expectException(ValidationException::class);

        $this->valorFinal($this->financeiro(1000, 0, -10));
    }

    public function test_porcentagem_acima_de_100_continua_recusada(): void
    {
        $this->expectException(ValidationException::class);

        $this->valorFinal($this->financeiro(1000, 150, 0, 'porcentagem'));
    }
}
