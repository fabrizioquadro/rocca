<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RemanejarSemanasTest extends TestCase
{
    use DatabaseTransactions;

    private const ATRASO = 5;

    /**
     * Prescrição com semanas agendadas e itens: é o cenário real do
     * remanejamento (capítulos seguintes ainda por aplicar).
     */
    private function prescricaoAgendada(): ?Prescricao
    {
        return Prescricao::with(['semanas.parcelas', 'semanas.itens'])
            ->orderByDesc('id')
            ->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas->count() >= 4
                && $prescricao->semanas->every(fn (PrescricaoSemana $semana) => $semana->status === StatusSemana::Agendada
                    && $semana->itens->isNotEmpty()));
    }

    private function administrador(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first();
    }

    /**
     * Deixa a primeira semana com ATRASO dias de atraso e a parcela quitada
     * (envio liberado sem senha de administrador).
     */
    private function prepararCenario(Prescricao $prescricao): PrescricaoSemana
    {
        $alvo = $prescricao->semanas->sortBy('numero')->first();

        $alvo->update([
            'status' => StatusSemana::Agendada,
            'data_prevista' => now()->startOfDay()->subDays(self::ATRASO),
            'chegada_em' => null,
            'liberado_por_user_id' => null,
            'liberado_em' => null,
        ]);

        $parcela = $alvo->parcelas()->first();

        if ($parcela) {
            $parcela->valor_pago = $parcela->valor;
            $parcela->save();
        }

        return $alvo->refresh();
    }

    /**
     * @return array<int, string> id da semana => data prevista (Y-m-d)
     */
    private function datasDasSemanas(Prescricao $prescricao, PrescricaoSemana $alvo): array
    {
        return $prescricao->semanas
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
        $seguintes = $prescricao->semanas
            ->filter(fn (PrescricaoSemana $semana) => $semana->numero > $alvo->numero && $semana->data_prevista)
            ->sortBy('numero')
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
