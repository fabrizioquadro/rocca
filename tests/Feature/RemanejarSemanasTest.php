<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\StatusSemana;
use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaItem;
use App\Models\User;
use App\Services\FinanceiroPagamentoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Collection;
use Tests\TestCase;

class RemanejarSemanasTest extends TestCase
{
    use DatabaseTransactions;

    private const ATRASO = 5;

    /**
     * Prescrição com pelo menos 4 semanas "limpas" (com itens e sem nenhuma
     * aplicação registrada): é o cenário do remanejamento, as semanas que
     * ainda estão por aplicar.
     */
    private function prescricaoAgendada(): ?Prescricao
    {
        return Prescricao::with(['semanas.parcelas', 'semanas.itens.aplicacoes'])
            ->orderByDesc('id')
            ->get()
            ->first(fn (Prescricao $prescricao) => $this->semanasLimpa($prescricao)->count() >= 4);
    }

    /**
     * Semanas sem nenhuma aplicação registrada — são as que o remanejamento
     * pode deslocar (as já aplicadas ficam como estão).
     *
     * @return Collection<int, PrescricaoSemana>
     */
    private function semanasLimpa(Prescricao $prescricao): Collection
    {
        return $prescricao->semanas
            ->filter(fn (PrescricaoSemana $semana) => $semana->itens->isNotEmpty()
                && $semana->itens->every(fn (PrescricaoSemanaItem $item) => $item->aplicacoes->isEmpty()))
            ->sortBy(fn (PrescricaoSemana $semana) => (int) $semana->numero)
            ->values();
    }

    private function administrador(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first();
    }

    /**
     * As semanas limpas voltam para "Agendada" e a primeira delas fica com
     * ATRASO dias de atraso. As semanas já aplicadas continuam como estão.
     */
    private function prepararCenario(Prescricao $prescricao): PrescricaoSemana
    {
        $semanas = $this->semanasLimpa($prescricao);

        $semanas->each(fn (PrescricaoSemana $semana) => $semana->update([
            'status' => StatusSemana::Agendada,
            'sem_aplicacao' => false,
            'chegada_em' => null,
            'liberado_por_user_id' => null,
            'liberado_em' => null,
        ]));

        $alvo = $semanas->first();

        $alvo->update(['data_prevista' => now()->startOfDay()->subDays(self::ATRASO)]);

        return $alvo->refresh();
    }

    /**
     * Recebe o valor em aberto do financeiro (como a Secretária faz na tela):
     * sem isso o envio para a fila exigiria a senha de um administrador.
     * O "pago" de cada parcela é recalculado a partir dos recebimentos.
     */
    private function quitarFinanceiro(Prescricao $prescricao, User $usuario): void
    {
        $financeiro = $prescricao->financeiro()->first();
        $aberto = $financeiro ? (float) $financeiro->valor_aberto : 0;

        if (! $financeiro || $aberto <= 0) {
            return;
        }

        $this->actingAs($usuario);

        app(FinanceiroPagamentoService::class)->registrar($financeiro, [
            'valor' => $aberto,
            'forma_pagamento' => FormaPagamento::Dinheiro->value,
        ]);
    }

    /**
     * @return array<int, string> id da semana => data prevista (Y-m-d)
     */
    private function datasDasSemanas(Prescricao $prescricao, PrescricaoSemana $alvo): array
    {
        return $this->semanasLimpa($prescricao)
            ->filter(fn (PrescricaoSemana $semana) => $semana->numero > $alvo->numero && $semana->data_prevista)
            ->mapWithKeys(fn (PrescricaoSemana $semana) => [$semana->id => $semana->data_prevista->toDateString()])
            ->all();
    }

    public function test_proposta_de_remanejamento_usa_o_atraso_da_semana(): void
    {
        $prescricao = $this->prescricaoAgendada();

        if (! $prescricao) {
            $this->markTestSkipped('Sem prescrição agendada com itens na base.');
        }

        $alvo = $this->prepararCenario($prescricao);
        $seguintes = $this->semanasLimpa($prescricao)
            ->filter(fn (PrescricaoSemana $semana) => $semana->numero > $alvo->numero && $semana->data_prevista)
            ->values();

        $this->assertSame(self::ATRASO, $alvo->dias_de_atraso);

        $proposta = $alvo->remanejamento;

        $this->assertSame(self::ATRASO, $proposta['atraso']);
        $this->assertCount($seguintes->count(), $proposta['semanas']);

        foreach ($seguintes as $indice => $semana) {
            $this->assertSame((int) $semana->numero, $proposta['semanas'][$indice]['numero']);
            $this->assertSame($semana->data_prevista->format('d/m/Y'), $proposta['semanas'][$indice]['de']);
            $this->assertSame(
                $semana->data_prevista->copy()->addDays(self::ATRASO)->format('d/m/Y'),
                $proposta['semanas'][$indice]['para'],
                'Semana '.$semana->numero.' deveria sair de '.$proposta['semanas'][$indice]['de']
            );
        }
    }

    public function test_semana_em_dia_nao_tem_proposta(): void
    {
        $prescricao = $this->prescricaoAgendada();

        if (! $prescricao) {
            $this->markTestSkipped('Sem prescrição agendada com itens na base.');
        }

        $alvo = $this->prepararCenario($prescricao);

        $alvo->update(['data_prevista' => now()->startOfDay()]);

        $emDia = $alvo->refresh();

        $this->assertSame(0, $emDia->dias_de_atraso);
        $this->assertSame(['atraso' => 0, 'semanas' => []], $emDia->remanejamento);
    }

    public function test_envio_de_semana_atrasada_remaneja_as_seguintes(): void
    {
        $prescricao = $this->prescricaoAgendada();
        $usuario = $this->administrador();

        if (! $prescricao || ! $usuario) {
            $this->markTestSkipped('Sem prescrição agendada ou administrador na base.');
        }

        $alvo = $this->prepararCenario($prescricao);
        $this->quitarFinanceiro($prescricao, $usuario);
        $datasAntes = $this->datasDasSemanas($prescricao, $alvo);

        // A tela da semana mostra a proposta no formulário de envio (JSON lido pelo JS)
        $proposta = $alvo->remanejamento;
        $propostaJson = json_encode($proposta);

        $this->actingAs($usuario)
            ->get(route('prescricoes.semanas.show', [$prescricao, $alvo]))
            ->assertOk()
            ->assertSee('data-fila-remanejar', false)
            ->assertSee('modal-remanejar', false)
            ->assertSee('Remanejar as semanas seguintes?', false)
            ->assertSee(e($propostaJson), false);

        // A listagem da prescrição também oferece o envio com a proposta
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', $prescricao))
            ->assertOk()
            ->assertSee(e($propostaJson), false);

        $resposta = $this->actingAs($usuario)->post(
            route('prescricoes.semanas.fila', [$prescricao, $alvo]),
            ['remanejar' => 1]
        );

        $resposta->assertRedirect(route('prescricoes.semanas.show', [$prescricao, $alvo]));

        $this->assertSame(StatusSemana::FilaAplicacao, $alvo->refresh()->status);
        $this->assertStringContainsString('remanejadas em '.self::ATRASO, session('success'));

        foreach ($datasAntes as $semanaId => $dataAntes) {
            $esperado = date('Y-m-d', strtotime($dataAntes.' +'.self::ATRASO.' days'));
            $semana = PrescricaoSemana::find($semanaId);

            $this->assertSame($esperado, $semana->data_prevista->toDateString());

            if ($parcela = $semana->parcelas()->first()) {
                $this->assertSame($esperado, $parcela->vencimento->toDateString(), 'Vencimento da semana '.$semana->numero);
            }
        }

        $this->assertDatabaseHas('prescricao_logs', [
            'prescricao_id' => $prescricao->id,
            'prescricao_semana_id' => $alvo->id,
            'acao' => 'remanejamento',
        ]);

        // O histórico da prescrição mostra o remanejamento com as datas novas
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'logs']))
            ->assertOk()
            ->assertSee('Semanas remanejadas')
            ->assertSee('Semanas seguintes remanejadas em '.self::ATRASO)
            ->assertSee($proposta['semanas'][0]['de'].' para '.$proposta['semanas'][0]['para']);
    }

    public function test_envio_de_semana_atrasada_sem_remanejar_mantem_as_datas(): void
    {
        $prescricao = $this->prescricaoAgendada();
        $usuario = $this->administrador();

        if (! $prescricao || ! $usuario) {
            $this->markTestSkipped('Sem prescrição agendada ou administrador na base.');
        }

        $alvo = $this->prepararCenario($prescricao);
        $this->quitarFinanceiro($prescricao, $usuario);
        $datasAntes = $this->datasDasSemanas($prescricao, $alvo);

        $this->actingAs($usuario)
            ->post(route('prescricoes.semanas.fila', [$prescricao, $alvo]), ['remanejar' => 0])
            ->assertRedirect(route('prescricoes.semanas.show', [$prescricao, $alvo]));

        $this->assertSame(StatusSemana::FilaAplicacao, $alvo->refresh()->status);
        $this->assertStringNotContainsString('remanejadas', (string) session('success'));

        foreach ($datasAntes as $semanaId => $dataAntes) {
            $semana = PrescricaoSemana::find($semanaId);

            $this->assertSame($dataAntes, $semana->data_prevista->toDateString());

            if ($parcela = $semana->parcelas()->first()) {
                $this->assertSame($dataAntes, $parcela->vencimento->toDateString());
            }
        }

        $this->assertDatabaseMissing('prescricao_logs', [
            'prescricao_id' => $prescricao->id,
            'acao' => 'remanejamento',
        ]);
    }

    public function test_envio_de_semana_em_dia_nao_remaneja(): void
    {
        $prescricao = $this->prescricaoAgendada();
        $usuario = $this->administrador();

        if (! $prescricao || ! $usuario) {
            $this->markTestSkipped('Sem prescrição agendada ou administrador na base.');
        }

        $alvo = $this->prepararCenario($prescricao);
        $this->quitarFinanceiro($prescricao, $usuario);

        $alvo->update(['data_prevista' => now()->startOfDay()]);

        $alvo->refresh();

        $datasAntes = $this->datasDasSemanas($prescricao, $alvo);

        $this->actingAs($usuario)
            ->post(route('prescricoes.semanas.fila', [$prescricao, $alvo]), ['remanejar' => 1])
            ->assertRedirect(route('prescricoes.semanas.show', [$prescricao, $alvo]));

        foreach ($datasAntes as $semanaId => $dataAntes) {
            $this->assertSame($dataAntes, PrescricaoSemana::find($semanaId)->data_prevista->toDateString());
        }

        $this->assertDatabaseMissing('prescricao_logs', [
            'prescricao_id' => $prescricao->id,
            'acao' => 'remanejamento',
        ]);
    }
}
