<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoAtendimento;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\User;
use App\Services\PrescricaoSemanaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Fila da Enfermagem: só entra quem CHEGOU (Fila de Aplicação). A semana com
 * aplicação parcial é o paciente que foi atendido e voltou para casa — ela só
 * reaparece na fila quando voltar e for enviada de novo.
 */
class EnfermagemFilaTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first()
            ?? User::where('tipo', TipoUsuario::Enfermagem->value)->first()
            ?? User::orderBy('id')->first();
    }

    /**
     * Prescrição com 1 semana pronta para a fila e outra com aplicação parcial.
     *
     * @return array{usuario: User, naFila: PrescricaoSemana, parcial: PrescricaoSemana}
     */
    private function cenario(): array
    {
        $usuario = $this->usuario();
        $paciente = Paciente::orderBy('id')->first();
        $clinica = Clinica::orderBy('id')->first();
        $medicamento = Medicamento::orderByDesc('id')->first();

        if (! $usuario || ! $paciente || ! $clinica || ! $medicamento) {
            $this->markTestSkipped('Sem usuário, paciente, clínica ou medicamento na base.');
        }

        $prescricao = Prescricao::create([
            'paciente_id' => $paciente->id,
            'medico_nome' => 'Dr. Teste da Enfermagem',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::ConsultaTratamento,
            'user_id' => $usuario->id,
        ]);

        $semanas = [];

        foreach ([1, 2] as $numero) {
            $semana = $prescricao->semanas()->create([
                'numero' => $numero,
                'data_prevista' => now()->addWeeks($numero)->startOfDay()->toDateString(),
                'sem_aplicacao' => false,
                'status' => StatusSemana::Agendada,
            ]);

            $semana->itens()->create([
                'tipo' => 'medicamento',
                'medicamento_id' => $medicamento->id,
                'quantidade' => 1,
                'valor' => 100,
                'gera_aplicacao' => true,
                'status' => StatusSemanaItem::Aberto,
            ]);

            $semanas[$numero] = $semana;
        }

        app(PrescricaoSemanaService::class)->sincronizarFinanceiro($prescricao->fresh());

        // Semana 1: paciente chegou e está esperando na fila
        $semanas[1]->update([
            'status' => StatusSemana::FilaAplicacao,
            'chegada_em' => now()->subMinutes(20),
        ]);

        // Semana 2: foi atendido parcialmente e voltou para casa
        $semanas[2]->update([
            'status' => StatusSemana::AplicacaoParcial,
            'chegada_em' => now()->subDays(2),
        ]);

        return [
            'usuario' => $usuario,
            'naFila' => $semanas[1]->fresh(),
            'parcial' => $semanas[2]->fresh(),
        ];
    }

    public function test_fila_da_enfermagem_lista_somente_quem_esta_na_fila_de_aplicacao(): void
    {
        ['usuario' => $usuario, 'naFila' => $naFila, 'parcial' => $parcial] = $this->cenario();

        $resposta = $this->actingAs($usuario)->get(route('enfermagem.index'));

        $resposta->assertOk();

        $ids = collect($resposta->viewData('fila'))->pluck('id')->all();

        $this->assertContains($naFila->id, $ids, 'Quem chegou e está esperando deve aparecer na fila.');
        $this->assertNotContains($parcial->id, $ids, 'Aplicação parcial (paciente em casa) não é fila de espera.');
    }

    public function test_semana_com_aplicacao_parcial_nao_mostra_espera_na_fila(): void
    {
        ['usuario' => $usuario, 'parcial' => $parcial] = $this->cenario();

        // A chegada antiga não vira tempo de espera
        $this->assertNull($parcial->tempo_de_espera);

        $resposta = $this->actingAs($usuario)->get(route('enfermagem.index'));
        $resposta->assertOk();

        $this->assertNotContains($parcial->id, collect($resposta->viewData('fila'))->pluck('id')->all());
    }

    public function test_ao_voltar_a_semana_parcial_entra_na_fila_com_nova_chegada(): void
    {
        ['usuario' => $usuario, 'parcial' => $parcial] = $this->cenario();

        $parcial->parcelas()->update(['valor_pago' => 100, 'status' => 'paga']);

        $this->actingAs($usuario)
            ->post(route('prescricoes.semanas.fila', [$parcial->prescricao, $parcial]))
            ->assertSessionHasNoErrors();

        $retornou = $parcial->fresh();

        $this->assertSame(StatusSemana::FilaAplicacao, $retornou->status);
        $this->assertTrue($retornou->chegada_em->isToday());

        $ids = collect($this->actingAs($usuario)->get(route('enfermagem.index'))->viewData('fila'))->pluck('id')->all();

        $this->assertContains($retornou->id, $ids, 'Depois de voltar, a semana entra na fila de aplicação.');
    }
}
