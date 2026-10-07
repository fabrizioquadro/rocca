<?php

namespace Tests\Unit;

use App\Enums\StatusSemana;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use Tests\TestCase;

/**
 * Data Aplicação da listagem: a data prevista da semana agendada mais antiga
 * (a aplicação é sequencial).
 */
class ProximaAplicacaoTest extends TestCase
{
    private function semana(StatusSemana $status, ?string $data, int $numero): PrescricaoSemana
    {
        return new PrescricaoSemana([
            'numero' => $numero,
            'data_prevista' => $data,
            'status' => $status,
        ]);
    }

    private function prescricao(PrescricaoSemana ...$semanas): Prescricao
    {
        $prescricao = new Prescricao();
        $prescricao->setRelation('semanas', collect($semanas));

        return $prescricao;
    }

    public function test_usa_a_semana_agendada_mais_antiga(): void
    {
        // A semana 2 vem antes da 3 na aplicação, mesmo com data posterior
        $prescricao = $this->prescricao(
            $this->semana(StatusSemana::Aplicada, '2026-09-25', 1),
            $this->semana(StatusSemana::Agendada, '2026-10-10', 2),
            $this->semana(StatusSemana::Agendada, '2026-10-03', 3),
        );

        $this->assertSame('2026-10-10', $prescricao->proxima_aplicacao?->toDateString());
        $this->assertSame('10/10/2026', $prescricao->proxima_aplicacao_formatada);
    }

    public function test_ignora_semana_agendada_sem_data_prevista(): void
    {
        $prescricao = $this->prescricao(
            $this->semana(StatusSemana::Agendada, null, 1),
            $this->semana(StatusSemana::Agendada, '2026-10-03', 2),
        );

        $this->assertSame('03/10/2026', $prescricao->proxima_aplicacao_formatada);
    }

    public function test_sem_semana_agendada_nao_tem_data(): void
    {
        $prescricao = $this->prescricao(
            $this->semana(StatusSemana::Aplicada, '2026-09-25', 1),
            $this->semana(StatusSemana::FilaAplicacao, '2026-10-01', 2),
            $this->semana(StatusSemana::Atendimento, '2026-10-08', 3),
        );

        $this->assertNull($prescricao->proxima_aplicacao);
        $this->assertNull($prescricao->proxima_aplicacao_formatada);
    }
}
