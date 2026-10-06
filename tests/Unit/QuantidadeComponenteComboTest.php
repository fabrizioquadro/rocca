<?php

namespace Tests\Unit;

use App\Enums\TipoMedicamento;
use App\Models\ComboItem;
use App\Models\Medicamento;
use App\Models\PrescricaoSemanaItem;
use Tests\TestCase;

/**
 * Quantidade de cada medicamento do combo: quantidade prescrita do combo
 * vezes a quantidade do medicamento no combo (ampola arredonda para cima).
 */
class QuantidadeComponenteComboTest extends TestCase
{
    private function componente(float $quantidade, ?TipoMedicamento $tipo = null): ComboItem
    {
        $componente = new ComboItem();
        $componente->quantidade = $quantidade;

        $medicamento = new Medicamento();

        if ($tipo) {
            $medicamento->tipo = $tipo;
        }

        $componente->setRelation('medicamento', $medicamento);

        return $componente;
    }

    private function itemCombo(float $quantidade): PrescricaoSemanaItem
    {
        $item = new PrescricaoSemanaItem();
        $item->tipo = 'combo';
        $item->quantidade = $quantidade;

        return $item;
    }

    public function test_multiplica_pela_quantidade_prescrita_do_combo(): void
    {
        $this->assertSame(6.0, $this->itemCombo(2)->quantidadeDoComponente($this->componente(3, TipoMedicamento::Miligrama)));
        $this->assertSame(30.0, $this->itemCombo(1)->quantidadeDoComponente($this->componente(30, TipoMedicamento::Miligrama)));
        $this->assertSame(1.0, $this->itemCombo(2)->quantidadeDoComponente($this->componente(0.5)));
    }

    public function test_ampola_nao_tem_fracao(): void
    {
        $this->assertSame(2.0, $this->itemCombo(3)->quantidadeDoComponente($this->componente(0.5, TipoMedicamento::Ampola)));
    }
}
