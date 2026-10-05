<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\TipoUsuario;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\User;
use Tests\TestCase;

class PrescricaoAbertaTest extends TestCase
{
    private function semana(StatusSemana $status, bool $semAplicacao = false): PrescricaoSemana
    {
        $semana = new PrescricaoSemana();
        $semana->status = $status;
        $semana->sem_aplicacao = $semAplicacao;

        return $semana;
    }

    private function prescricao(array $semanas): Prescricao
    {
        $prescricao = new Prescricao();
        $prescricao->setRelation('semanas', collect($semanas));

        return $prescricao;
    }

    public function test_regra_de_prescricao_aberta(): void
    {
        // Abertas: ainda existe semana com aplicacao pendente
        $this->assertTrue($this->prescricao([
            $this->semana(StatusSemana::Aplicada),
            $this->semana(StatusSemana::Agendada),
        ])->estaAberta());

        $this->assertTrue($this->prescricao([$this->semana(StatusSemana::Agendada)])->estaAberta());
        $this->assertTrue($this->prescricao([$this->semana(StatusSemana::FilaAplicacao)])->estaAberta());
        $this->assertTrue($this->prescricao([$this->semana(StatusSemana::Atendimento)])->estaAberta());

        // Fechadas: tudo que tinha aplicacao ja foi concluido
        $this->assertFalse($this->prescricao([
            $this->semana(StatusSemana::Aplicada),
            $this->semana(StatusSemana::Aplicada),
        ])->estaAberta());

        // Aplicacao parcial conta como finalizada (mesma regra da situacao do
        // sistema: "Finalizado com pendencia")
        $this->assertFalse($this->prescricao([$this->semana(StatusSemana::AplicacaoParcial)])->estaAberta());

        $this->assertFalse($this->prescricao([
            $this->semana(StatusSemana::Aplicada),
            $this->semana(StatusSemana::Aplicada, true),
        ])->estaAberta());

        // Sem nada para aplicar nao fica em aberto
        $this->assertFalse($this->prescricao([$this->semana(StatusSemana::Aplicada, true)])->estaAberta());
    }

    public function test_endpoint_detecta_prescricao_aberta(): void
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first();

        $aberta = Prescricao::with('semanas')->orderByDesc('id')->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->estaAberta());

        if (! $usuario || ! $aberta) {
            $this->markTestSkipped('Sem prescrição aberta na base.');
        }

        $resposta = $this->actingAs($usuario)
            ->getJson('/prescricoes/pacientes/'.$aberta->paciente_id.'/aberta')
            ->assertOk()
            ->assertJson(['aberta' => true]);

        $this->assertGreaterThan(0, $resposta->json('id'));
        $this->assertNotSame('Finalizado', $resposta->json('situacao'));
    }

    public function test_endpoint_sem_prescricao_aberta(): void
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first();
        $paciente = Paciente::whereNotIn('id', Prescricao::pluck('paciente_id'))->first();

        if (! $usuario || ! $paciente) {
            $this->markTestSkipped('Sem paciente sem prescrição na base.');
        }

        $this->actingAs($usuario)
            ->getJson('/prescricoes/pacientes/'.$paciente->id.'/aberta')
            ->assertOk()
            ->assertJson(['aberta' => false]);
    }

    public function test_endpoint_ignora_prescricao_ja_finalizada(): void
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário administrador.');
        }

        // Pacientes que têm prescrição, mas nenhuma em aberto
        $paciente = Prescricao::with('semanas')->get()
            ->groupBy('paciente_id')
            ->filter(fn ($lista) => $lista->every(fn (Prescricao $prescricao) => ! $prescricao->estaAberta()))
            ->keys()
            ->first();

        if (! $paciente) {
            $this->markTestSkipped('Sem paciente com todas as prescrições finalizadas.');
        }

        $this->actingAs($usuario)
            ->getJson('/prescricoes/pacientes/'.$paciente.'/aberta')
            ->assertOk()
            ->assertJson(['aberta' => false]);
    }
}
