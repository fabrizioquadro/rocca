<?php

namespace Tests\Unit;

use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\PrescricaoSemanaItem;
use Tests\TestCase;

/**
 * Data de aplicação da semana (coluna da listagem de semanas): o dia em que a
 * aplicação foi registrada, juntando os dias quando o paciente voltou.
 */
class DataAplicacaoSemanaTest extends TestCase
{
    private function aplicacao(string $aplicadoEm): PrescricaoSemanaAplicacao
    {
        $aplicacao = new PrescricaoSemanaAplicacao();
        $aplicacao->aplicado_em = $aplicadoEm;

        return $aplicacao;
    }

    private function item(PrescricaoSemanaAplicacao ...$aplicacoes): PrescricaoSemanaItem
    {
        $item = new PrescricaoSemanaItem();
        $item->setRelation('aplicacoes', collect($aplicacoes));

        return $item;
    }

    private function semana(PrescricaoSemanaItem ...$itens): PrescricaoSemana
    {
        $semana = new PrescricaoSemana();
        $semana->setRelation('itens', collect($itens));

        return $semana;
    }

    public function test_usa_o_dia_das_aplicacoes(): void
    {
        // Dois medicamentos aplicados no mesmo dia: uma data só
        $semana = $this->semana(
            $this->item($this->aplicacao('2026-10-10 09:15:00'), $this->aplicacao('2026-10-10 09:20:00')),
            $this->item($this->aplicacao('2026-10-10 10:00:00')),
        );

        $this->assertSame(1, $semana->datas_de_aplicacao->count());
        $this->assertSame('10/10/2026', $semana->data_aplicacao_formatada);
    }

    public function test_junta_os_dias_da_aplicacao_parcial(): void
    {
        // Paciente voltou em outro dia para completar a aplicação
        $semana = $this->semana(
            $this->item($this->aplicacao('2026-10-12 14:00:00')),
            $this->item($this->aplicacao('2026-10-10 09:15:00')),
        );

        $this->assertSame(['10/10/2026', '12/10/2026'], $semana->datas_de_aplicacao
            ->map(fn ($data) => $data->format('d/m/Y'))
            ->all());

        $this->assertSame('10/10/2026 · 12/10/2026', $semana->data_aplicacao_formatada);
    }

    public function test_semana_sem_aplicacao_nao_tem_data(): void
    {
        // Semana só de itens que não geram aplicação (sem atendimento)
        $semana = $this->semana($this->item());

        $this->assertTrue($semana->datas_de_aplicacao->isEmpty());
        $this->assertNull($semana->data_aplicacao_formatada);
    }
}
