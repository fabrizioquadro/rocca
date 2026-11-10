<?php

namespace Tests\Feature;

use App\Models\Medicamento;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaItem;
use Tests\TestCase;

/**
 * Coluna "Procedimento" do relatório de Recebimentos: Consulta quando a
 * prescrição tem somente o medicamento Consulta; qualquer outro item
 * (medicamento ou combo) faz dela um Tratamento.
 */
class ClassificacaoPrescricaoTest extends TestCase
{
    /**
     * Prescrição em memória com os itens informados (null = item de combo).
     *
     * @param  array<int, int|null>  $medicamentoIds
     */
    private function prescricao(array $medicamentoIds): Prescricao
    {
        $itens = collect($medicamentoIds)->map(function (?int $medicamentoId) {
            $item = new PrescricaoSemanaItem();
            $item->tipo = $medicamentoId === null ? 'combo' : 'medicamento';
            $item->medicamento_id = $medicamentoId;

            return $item;
        });

        $semana = new PrescricaoSemana();
        $semana->setRelation('itens', $itens);

        $prescricao = new Prescricao();
        $prescricao->setRelation('semanas', collect([$semana]));

        return $prescricao;
    }

    public function test_somente_o_medicamento_consulta_e_consulta(): void
    {
        $this->assertSame('Consulta', $this->prescricao([Medicamento::CONSULTA_ID])->classificacao);

        // A Consulta pode estar repetida em mais de uma semana
        $this->assertSame('Consulta', $this->prescricao([
            Medicamento::CONSULTA_ID,
            Medicamento::CONSULTA_ID,
        ])->classificacao);
    }

    public function test_qualquer_outro_item_faz_da_prescricao_tratamento(): void
    {
        $this->assertSame('Tratamento', $this->prescricao([Medicamento::CONSULTA_ID, 26])->classificacao);
        $this->assertSame('Tratamento', $this->prescricao([26])->classificacao);

        // Item de combo não tem medicamento: não é consulta
        $this->assertSame('Tratamento', $this->prescricao([Medicamento::CONSULTA_ID, null])->classificacao);
        $this->assertSame('Tratamento', $this->prescricao([null])->classificacao);
    }

    public function test_prescricao_sem_itens_nao_tem_classificacao(): void
    {
        $this->assertNull($this->prescricao([])->classificacao);
    }
}
