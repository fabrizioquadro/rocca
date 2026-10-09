<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\TipoAtendimento;
use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\User;
use App\Services\FinanceiroPagamentoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Filtros do relatório de Recebimentos: médico, forma de pagamento, tipo de
 * atendimento e paciente (além do período e da clínica que já existiam).
 */
class FiltrosRecebimentosTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Duas prescrições com financeiro, cada uma com um pagamento de valor
     * conhecido — o identificador é o que diferencia as linhas no relatório.
     *
     * @return array{0: User, 1: Prescricao, 2: Prescricao}
     */
    private function cenario(): array
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();

        $comFinanceiro = Prescricao::with('financeiro.parcelas')->get()
            ->filter(fn (Prescricao $prescricao) => $prescricao->financeiro !== null
                && $prescricao->financeiro->parcelas->isNotEmpty())
            ->values();

        if (! $usuario || $comFinanceiro->count() < 2) {
            $this->markTestSkipped('Sem duas prescrições com financeiro na base.');
        }

        [$a, $b] = [$comFinanceiro[0], $comFinanceiro[1]];

        $this->actingAs($usuario);

        // Cada prescrição com médico, atendimento e paciente distintos
        $a->update([
            'medico_nome' => 'Dra. Alfa Filtro',
            'tipo_atendimento' => TipoAtendimento::Retorno,
        ]);

        $b->update([
            'medico_nome' => 'Dr. Beta Filtro',
            'tipo_atendimento' => TipoAtendimento::ColetaBio,
        ]);

        if ((int) $b->paciente_id === (int) $a->paciente_id) {
            $b->update(['paciente_id' => Prescricao::where('paciente_id', '!=', $a->paciente_id)->value('paciente_id')]);
        }

        app(FinanceiroPagamentoService::class)->registrar($a->financeiro, [
            'valor' => '111,00',
            'forma_pagamento' => FormaPagamento::Pix->value,
            'data_pagamento' => now()->toDateString(),
            'identificador' => 'PIX-ALFA',
        ]);

        app(FinanceiroPagamentoService::class)->registrar($b->financeiro, [
            'valor' => '222,00',
            'forma_pagamento' => FormaPagamento::CartaoCredito->value,
            'parcelas' => 2,
            'data_pagamento' => now()->toDateString(),
            'identificador' => 'CARD-BETA',
        ]);

        return [$usuario, $a->refresh(), $b->refresh()];
    }

    public function test_filtros_aparecem_no_relatorio(): void
    {
        [$usuario] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('relatorios.recebimentos'));

        $pagina->assertOk();

        foreach (['medico', 'forma_pagamento', 'tipo_atendimento', 'paciente_id'] as $campo) {
            $pagina->assertSee('name="'.$campo.'"', false);
        }

        $pagina->assertSee('Médico')
            ->assertSee('Forma de pagamento')
            ->assertSee('Tipo de atendimento')
            ->assertSee('Paciente');
    }

    public function test_sem_filtro_mostra_os_dois_recebimentos(): void
    {
        [$usuario] = $this->cenario();

        $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos'))
            ->assertOk()
            ->assertSee('PIX-ALFA')
            ->assertSee('CARD-BETA');
    }

    public function test_filtra_por_medico(): void
    {
        [$usuario] = $this->cenario();

        $pagina = $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', ['medico' => 'Dra. Alfa Filtro']));

        $pagina->assertOk()
            ->assertSee('PIX-ALFA')
            ->assertDontSee('CARD-BETA');

        // O select traz os médicos do período e mantém o escolhido marcado
        $pagina->assertSee('option value="Dra. Alfa Filtro" selected', false)
            ->assertSee('option value="Dr. Beta Filtro"', false);
    }

    public function test_filtra_por_forma_de_pagamento(): void
    {
        [$usuario] = $this->cenario();

        $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', ['forma_pagamento' => FormaPagamento::Pix->value]))
            ->assertOk()
            ->assertSee('PIX-ALFA')
            ->assertDontSee('CARD-BETA');
    }

    public function test_filtra_por_tipo_de_atendimento(): void
    {
        [$usuario] = $this->cenario();

        $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', ['tipo_atendimento' => TipoAtendimento::ColetaBio->value]))
            ->assertOk()
            ->assertSee('CARD-BETA')
            ->assertDontSee('PIX-ALFA');
    }

    public function test_filtra_por_paciente(): void
    {
        [$usuario, $a] = $this->cenario();

        $pagina = $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', ['paciente_id' => $a->paciente_id]));

        $pagina->assertOk()
            ->assertSee('PIX-ALFA')
            ->assertDontSee('CARD-BETA');

        // O paciente escolhido continua selecionado no filtro
        $pagina->assertSee('option value="'.$a->paciente_id.'" selected', false);
    }

    public function test_filtros_combinados(): void
    {
        [$usuario, $a] = $this->cenario();

        $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', [
                'medico' => 'Dra. Alfa Filtro',
                'forma_pagamento' => FormaPagamento::Pix->value,
                'tipo_atendimento' => TipoAtendimento::Retorno->value,
                'paciente_id' => $a->paciente_id,
            ]))
            ->assertOk()
            ->assertSee('PIX-ALFA')
            ->assertDontSee('CARD-BETA');

        // Combinação que não existe: nenhum recebimento
        $this->actingAs($usuario)
            ->get(route('relatorios.recebimentos', [
                'medico' => 'Dra. Alfa Filtro',
                'forma_pagamento' => FormaPagamento::CartaoCredito->value,
            ]))
            ->assertOk()
            ->assertSee('Nenhum recebimento no período.')
            ->assertDontSee('PIX-ALFA')
            ->assertDontSee('CARD-BETA');
    }
}
