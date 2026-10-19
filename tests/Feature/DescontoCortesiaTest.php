<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoAtendimento;
use App\Enums\TipoLogPrescricao;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\User;
use App\Services\PrescricaoSemanaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Cortesia no financeiro: o desconto pode zerar o total (é o que a clínica
 * aprova) e o painel "Editar desconto/adicional" não recusa mais esse caso.
 */
class DescontoCortesiaTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Prescrição com 2 semanas de R$ 100 (bruto R$ 200) e o financeiro gerado.
     *
     * @return array{0: User, 1: Prescricao}
     */
    private function cenario(): array
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
        $paciente = Paciente::orderBy('id')->first();
        $clinica = Clinica::orderBy('id')->first();
        $medicamento = Medicamento::orderByDesc('id')->first();

        if (! $usuario || ! $paciente || ! $clinica || ! $medicamento) {
            $this->markTestSkipped('Sem usuário, paciente, clínica ou medicamento na base.');
        }

        $prescricao = Prescricao::create([
            'paciente_id' => $paciente->id,
            'medico_nome' => 'Dr. Teste da Cortesia',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::ConsultaTratamento,
            'user_id' => $usuario->id,
        ]);

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
        }

        app(PrescricaoSemanaService::class)->sincronizarFinanceiro($prescricao->fresh());

        return [$usuario, $prescricao->fresh()];
    }

    public function test_desconto_igual_ao_total_e_aceito_e_zera_o_financeiro(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $financeiro = $prescricao->financeiro;
        $this->assertSame(200.0, (float) $financeiro->valor_bruto);

        $this->actingAs($usuario)
            ->put(route('prescricoes.financeiro.update', $prescricao), [
                'desconto_valor' => '200,00',
                'observacao' => 'Cortesia aprovada pela gerência',
            ])
            ->assertRedirect(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro']))
            ->assertSessionHas('success');

        $financeiro->refresh();

        $this->assertSame(200.0, (float) $financeiro->valor_bruto);
        $this->assertSame(200.0, $financeiro->valor_desconto);
        $this->assertSame(0.0, (float) $financeiro->valor_total);
        $this->assertSame(0.0, $financeiro->valor_aberto);

        // As parcelas continuam existindo, com valor zerado (nada a cobrar)
        $this->assertSame(2, $financeiro->parcelas()->count());

        foreach ($financeiro->parcelas as $parcela) {
            $this->assertSame(0.0, (float) $parcela->valor);
            $this->assertSame(0.0, (float) $parcela->valor_pago);
        }

        // O ajuste fica no histórico
        $this->assertTrue(
            PrescricaoLog::where('prescricao_id', $prescricao->id)
                ->where('acao', TipoLogPrescricao::FinanceiroAjustado->value)
                ->exists()
        );
    }

    public function test_desconto_maior_que_o_total_nao_deixa_valor_negativo(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $this->actingAs($usuario)
            ->put(route('prescricoes.financeiro.update', $prescricao), [
                'desconto_valor' => '500,00',
            ])
            ->assertSessionHas('success');

        $financeiro = $prescricao->refresh()->financeiro;

        $this->assertSame(0.0, (float) $financeiro->valor_total);

        foreach ($financeiro->parcelas as $parcela) {
            $this->assertSame(0.0, (float) $parcela->valor, 'Nenhuma parcela pode ficar negativa.');
        }
    }

    public function test_a_cortesia_sai_do_cadastro_da_prescricao_tambem(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        // 100% de desconto no cadastro: total zerado, sem erro
        $this->actingAs($usuario)
            ->put(route('prescricoes.financeiro.update', $prescricao), [
                'desconto_valor' => '200,00',
                'adicional_valor' => '0,00',
            ])
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, (float) $prescricao->refresh()->financeiro->valor_total);
    }

    public function test_desconto_negativo_e_ignorado_sem_quebrar_o_financeiro(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $this->actingAs($usuario)
            ->put(route('prescricoes.financeiro.update', $prescricao), [
                'desconto_valor' => '-10,00',
            ])
            ->assertSessionHasNoErrors();

        $financeiro = $prescricao->refresh()->financeiro;

        $this->assertSame(200.0, (float) $financeiro->valor_total);
        $this->assertSame(0.0, $financeiro->valor_desconto);
    }
}
