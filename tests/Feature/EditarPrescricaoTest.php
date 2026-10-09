<?php

namespace Tests\Feature;

use App\Enums\TipoAtendimento;
use App\Enums\TipoLogPrescricao;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Edição dos dados do cabeçalho da prescrição (médico, clínica, tipo de
 * atendimento, agendamento e observações), com histórico das alterações.
 */
class EditarPrescricaoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(TipoUsuario $tipo = TipoUsuario::Administrador): ?User
    {
        return User::where('tipo', $tipo->value)->first() ?? User::where('tipo', TipoUsuario::Administrador->value)->first();
    }

    private function prescricao(): ?Prescricao
    {
        return Prescricao::orderByDesc('id')->first();
    }

    public function test_formulario_de_edicao_abre_com_os_dados_atuais(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.edit', $prescricao));

        $pagina->assertOk()
            ->assertSee('Editar prescrição #'.$prescricao->id)
            ->assertSee($prescricao->paciente?->nome ?? '—');

        // Todos os campos editáveis estão no formulário
        foreach (['medico_id', 'medico_nome', 'clinica_id', 'tipo_atendimento', 'agendamento', 'observacoes'] as $campo) {
            $pagina->assertSee('name="'.$campo.'"', false);
        }

        // A tela da prescrição oferece o caminho para a edição
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', $prescricao))
            ->assertOk()
            ->assertSee('Editar prescrição');

        // E a listagem também (menu de ações da linha)
        $this->actingAs($usuario)
            ->get(route('prescricoes.index'))
            ->assertOk()
            ->assertSee(route('prescricoes.edit', $prescricao));
    }

    public function test_salva_os_dados_e_registra_no_historico(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();
        $clinica = Clinica::orderByDesc('id')->first();
        $outraClinica = Clinica::where('id', '!=', $clinica?->id)->first();

        if (! $usuario || ! $prescricao || ! $clinica) {
            $this->markTestSkipped('Sem usuário, prescrição ou clínica na base.');
        }

        $prescricao->update([
            'medico_id' => null,
            'medico_nome' => null,
            'agendamento' => null,
            'observacoes' => null,
        ]);

        $resposta = $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'medico_id' => '4321',
            'medico_nome' => 'Dra. Teste da Silva',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::Retorno->value,
            'agendamento' => 'Quinta-feira às 15h',
            'observacoes' => 'Ajustado pela tela de edição.',
        ]);

        $resposta->assertRedirect(route('prescricoes.show', $prescricao));

        $prescricao->refresh();

        // O médico que não está no cadastro local também é aceito (vem da Feegow)
        $this->assertSame(4321, (int) $prescricao->medico_id);
        $this->assertSame('Dra. Teste da Silva', $prescricao->medico_nome);
        $this->assertSame((int) $clinica->id, (int) $prescricao->clinica_id);
        $this->assertSame(TipoAtendimento::Retorno, $prescricao->tipo_atendimento);
        $this->assertSame('Quinta-feira às 15h', $prescricao->agendamento);
        $this->assertSame('Ajustado pela tela de edição.', $prescricao->observacoes);

        $log = PrescricaoLog::where('prescricao_id', $prescricao->id)
            ->where('acao', TipoLogPrescricao::Edicao->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'A edição deveria ficar no histórico.');

        $campos = collect($log->alteracoes)->pluck('campo')->all();

        foreach (['Médico', 'Tipo de atendimento', 'Agendamento', 'Observações'] as $campo) {
            $this->assertContains($campo, $campos);
        }

        $medico = collect($log->alteracoes)->firstWhere('campo', 'Médico');
        $this->assertSame('—', $medico['de']);
        $this->assertSame('Dra. Teste da Silva', $medico['para']);

        // O histórico mostra a alteração na tela
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'logs']))
            ->assertOk()
            ->assertSee('Prescrição editada')
            ->assertSee('Dra. Teste da Silva');
    }

    public function test_troca_de_clinica_acompanha_no_financeiro(): void
    {
        $usuario = $this->usuario();
        $prescricao = Prescricao::with('financeiro')->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->financeiro !== null);

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem prescrição com financeiro na base.');
        }

        // Duas clínicas: usa outra da base ou cria uma só para o teste
        // (a transação desfaz tudo no fim)
        $novaClinica = Clinica::where('id', '!=', $prescricao->clinica_id)->first()
            ?? Clinica::create([
                'nome' => 'Clínica do teste de edição',
                'cnpj' => '00000000000000',
                'id_feegow' => 999999,
            ]);

        $financeiro = $prescricao->financeiro;
        $valoresAntes = [
            'valor_bruto' => (float) $financeiro->valor_bruto,
            'valor_total' => (float) $financeiro->valor_total,
            'valor_pago' => (float) $financeiro->valor_pago,
            'parcelas' => $financeiro->parcelas()->count(),
        ];

        $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'clinica_id' => $novaClinica->id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            'medico_nome' => $prescricao->medico_nome,
        ])->assertRedirect(route('prescricoes.show', $prescricao));

        $financeiro->refresh();

        // A clínica muda, os valores e as parcelas continuam iguais
        $this->assertSame((int) $novaClinica->id, (int) $prescricao->refresh()->clinica_id);
        $this->assertSame((int) $novaClinica->id, (int) $financeiro->clinica_id);
        $this->assertSame($valoresAntes['valor_bruto'], (float) $financeiro->valor_bruto);
        $this->assertSame($valoresAntes['valor_total'], (float) $financeiro->valor_total);
        $this->assertSame($valoresAntes['valor_pago'], (float) $financeiro->valor_pago);
        $this->assertSame($valoresAntes['parcelas'], $financeiro->parcelas()->count());
    }

    public function test_campos_obrigatorios_sao_exigidos(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $this->actingAs($usuario)
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => '',
                'tipo_atendimento' => '',
            ])
            ->assertSessionHasErrors(['clinica_id', 'tipo_atendimento']);

        // Nada foi alterado
        $this->assertSame((int) $prescricao->clinica_id, (int) $prescricao->refresh()->clinica_id);
    }

    public function test_medico_em_branco_limpa_o_campo(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricao();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $prescricao->update(['medico_id' => 99, 'medico_nome' => 'Alguém']);

        $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'medico_id' => '',
            'medico_nome' => '',
            'clinica_id' => $prescricao->clinica_id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
        ])->assertRedirect(route('prescricoes.show', $prescricao));

        $prescricao->refresh();

        $this->assertNull($prescricao->medico_id);
        $this->assertNull($prescricao->medico_nome);
    }

    public function test_todos_os_perfis_podem_editar(): void
    {
        $prescricao = $this->prescricao();

        if (! $prescricao) {
            $this->markTestSkipped('Sem prescrição na base.');
        }

        foreach ([TipoUsuario::Administrador, TipoUsuario::Secretaria, TipoUsuario::Enfermagem] as $tipo) {
            $usuario = $this->usuario($tipo);

            if (! $usuario || $usuario->tipo !== $tipo) {
                continue;
            }

            $this->actingAs($usuario)
                ->get(route('prescricoes.edit', $prescricao))
                ->assertOk();

            $this->actingAs($usuario)
                ->put(route('prescricoes.update', $prescricao), [
                    'clinica_id' => $prescricao->clinica_id,
                    'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                ])
                ->assertRedirect(route('prescricoes.show', $prescricao));
        }
    }
}
