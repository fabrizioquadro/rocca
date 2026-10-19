<?php

namespace Tests\Feature;

use App\Enums\StatusParcela;
use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoAtendimento;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\FinanceiroParcela;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\User;
use App\Services\FinanceiroPagamentoService;
use App\Services\PrescricaoSemanaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Parcela vencida: SÓ quando o paciente foi aplicado e o valor continua em
 * aberto. Semana que não aconteceu (paciente não veio) fica "a vencer", mesmo
 * com o vencimento no passado.
 */
class ParcelaVencidaTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Prescrição com 2 semanas de R$ 200 vencendo no passado:
     * a semana 1 com aplicação registrada (Aplicada) e a semana 2 sem nada
     * (Agendada, o paciente não veio).
     *
     * @return array{usuario: User, prescricao: Prescricao, aplicada: FinanceiroParcela, naoAplicada: FinanceiroParcela}
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
            'medico_nome' => 'Dr. Teste do Vencimento',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::ConsultaTratamento,
            'user_id' => $usuario->id,
        ]);

        $semanas = [];

        foreach ([1, 2] as $numero) {
            $semana = $prescricao->semanas()->create([
                'numero' => $numero,
                'data_prevista' => now()->subDays(10 * (3 - $numero))->startOfDay()->toDateString(),
                'sem_aplicacao' => false,
                'status' => StatusSemana::Agendada,
            ]);

            $semana->itens()->create([
                'tipo' => 'medicamento',
                'medicamento_id' => $medicamento->id,
                'quantidade' => 1,
                'valor' => 200,
                'gera_aplicacao' => true,
                'status' => StatusSemanaItem::Aberto,
            ]);

            $semanas[$numero] = $semana;
        }

        app(PrescricaoSemanaService::class)->sincronizarFinanceiro($prescricao->fresh());

        // Semana 1: paciente veio e aplicou
        $this->aplicar($semanas[1]);

        $prescricao->refresh();

        $parcelas = $prescricao->financeiro->parcelas()->orderBy('numero')->get();

        return [
            'usuario' => $usuario,
            'prescricao' => $prescricao,
            'aplicada' => $parcelas->firstWhere('prescricao_semana_id', $semanas[1]->id),
            'naoAplicada' => $parcelas->firstWhere('prescricao_semana_id', $semanas[2]->id),
        ];
    }

    private function aplicar(PrescricaoSemana $semana): void
    {
        $item = $semana->itens()->first();

        $atendimento = $semana->atendimentos()->create([
            'iniciado_em' => now()->subHour(),
            'iniciado_por_user_id' => $this->usuario()?->id,
            'finalizado_em' => now(),
            'finalizado_por_user_id' => $this->usuario()?->id,
        ]);

        PrescricaoSemanaAplicacao::create([
            'prescricao_semana_atendimento_id' => $atendimento->id,
            'prescricao_semana_item_id' => $item->id,
            'medicamento_id' => $item->medicamento_id,
            'quantidade' => 1,
            'aplicado_em' => now(),
            'user_id' => $this->usuario()?->id,
        ]);

        $semana->update(['status' => StatusSemana::Aplicada]);
    }

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    private function pagar(FinanceiroParcela $parcela, float $valor): void
    {
        $financeiro = $parcela->financeiro;

        $financeiro->pagamentos()->create([
            'valor' => $valor,
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'dinheiro',
            'user_id' => $this->usuario()?->id,
        ]);

        app(FinanceiroPagamentoService::class)->reprocessar($financeiro->refresh());
    }

    public function test_parcela_de_semana_aplicada_e_nao_paga_esta_vencida(): void
    {
        ['aplicada' => $aplicada] = $this->cenario();

        $this->assertTrue($aplicada->fresh()->esta_vencida);
    }

    public function test_parcela_de_semana_nao_aplicada_nao_esta_vencida(): void
    {
        ['naoAplicada' => $naoAplicada] = $this->cenario();

        $parcela = $naoAplicada->fresh();

        $this->assertFalse($parcela->esta_vencida, 'Quem não foi aplicado não fica vencido.');
        $this->assertTrue($parcela->vencimento->lt(now()->startOfDay()), 'A data continua no passado.');
        $this->assertSame(StatusParcela::Aberta, $parcela->status);
    }

    public function test_parcela_de_semana_aplicada_e_paga_nao_esta_vencida(): void
    {
        ['aplicada' => $aplicada] = $this->cenario();

        $this->pagar($aplicada, 200.0);

        $parcela = $aplicada->fresh();

        $this->assertSame(StatusParcela::Paga, $parcela->status);
        $this->assertFalse($parcela->esta_vencida);
    }

    public function test_parcela_de_semana_aplicada_com_pagamento_parcial_esta_vencida_pelo_restante(): void
    {
        ['aplicada' => $aplicada] = $this->cenario();

        // O caso do cliente: aplicou e faltou R$ 200 dos R$ 200 (pagou nada)
        $this->pagar($aplicada, 80.0);

        $parcela = $aplicada->fresh();

        $this->assertSame(StatusParcela::Parcial, $parcela->status);
        $this->assertTrue($parcela->esta_vencida);
        $this->assertSame(120.0, $parcela->valor_em_aberto);
    }

    public function test_consulta_de_vencidas_traz_so_as_aplicadas(): void
    {
        ['prescricao' => $prescricao, 'aplicada' => $aplicada, 'naoAplicada' => $naoAplicada] = $this->cenario();

        $ids = FinanceiroParcela::vencidas()
            ->where('financeiro_id', $prescricao->financeiro->id)
            ->pluck('id')
            ->all();

        $this->assertSame([$aplicada->id], $ids);
        $this->assertNotContains($naoAplicada->id, $ids);
    }

    public function test_relatorio_de_contas_a_receber_separa_vencida_de_a_vencer(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'aplicada' => $aplicada, 'naoAplicada' => $naoAplicada] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('relatorios.contas-receber', [
            'inicio' => now()->subMonth()->toDateString(),
            'fim' => now()->toDateString(),
        ]));

        $pagina->assertOk()->assertSee('Vencida')->assertSee('A vencer');

        // O total vencido conta só a parcela da semana aplicada (R$ 200)
        $pagina->assertSee('R$ 200,00');
        $this->assertTrue($aplicada->fresh()->esta_vencida);
        $this->assertFalse($naoAplicada->fresh()->esta_vencida);
    }

    public function test_relatorio_com_filtro_so_vencidas_ignora_quem_nao_foi_aplicado(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'aplicada' => $aplicada, 'naoAplicada' => $naoAplicada] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('relatorios.contas-receber', [
            'inicio' => now()->subMonth()->toDateString(),
            'fim' => now()->toDateString(),
            'vencidas' => 1,
        ]));

        $pagina->assertOk()->assertSee('Vencida');

        // No financeiro desta prescrição só a parcela aplicada aparece como vencida
        $ids = FinanceiroParcela::vencidas()
            ->where('financeiro_id', $prescricao->financeiro->id)
            ->pluck('id')
            ->all();

        $this->assertSame([$aplicada->id], $ids);
        $this->assertNotContains($naoAplicada->id, $ids);
    }

    public function test_kpi_do_dashboard_nao_conta_quem_nao_foi_aplicado(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'aplicada' => $aplicada] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('home'));

        $pagina->assertOk()->assertSee('Vencido em aberto');

        // O KPI do dashboard usa o mesmo escopo: só entra a parcela aplicada
        $this->assertSame(200.0, round((float) FinanceiroParcela::vencidas()
            ->where('financeiro_id', $prescricao->financeiro->id)
            ->selectRaw('COALESCE(SUM(valor - valor_pago), 0) as aberto')
            ->value('aberto'), 2));

        $this->assertTrue($aplicada->fresh()->esta_vencida);
    }
}
