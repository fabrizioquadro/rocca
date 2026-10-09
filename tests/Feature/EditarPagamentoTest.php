<?php

namespace Tests\Feature;

use App\Enums\FormaPagamento;
use App\Enums\TipoLogPrescricao;
use App\Enums\TipoUsuario;
use App\Models\FinanceiroPagamento;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\User;
use App\Services\FinanceiroPagamentoService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Recebimentos com médico/atendimento no relatório e edição de um pagamento
 * já lançado (valor, forma, parcelas, data, ID e observação).
 */
class EditarPagamentoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    /**
     * Prescrição com financeiro e uma parcela em aberto: é onde o pagamento
     * é lançado e corrigido.
     */
    private function prescricaoComParcelas(): ?Prescricao
    {
        return Prescricao::with(['financeiro.parcelas', 'paciente'])
            ->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->financeiro !== null
                && $prescricao->financeiro->parcelas->isNotEmpty());
    }

    private function lancarPagamento(Prescricao $prescricao, float $valor): FinanceiroPagamento
    {
        return app(FinanceiroPagamentoService::class)->registrar($prescricao->financeiro, [
            'valor' => number_format($valor, 2, ',', '.'),
            'forma_pagamento' => FormaPagamento::Pix->value,
            'data_pagamento' => now()->toDateString(),
            'observacao' => 'Lançado no teste',
        ]);
    }

    public function test_edita_o_pagamento_e_recalcula_as_parcelas(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoComParcelas();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição com parcelas na base.');
        }

        $this->actingAs($usuario);

        $recebidoAntes = (float) $prescricao->financeiro->valor_recebido;
        $pagamento = $this->lancarPagamento($prescricao, 500);

        $this->assertSame($recebidoAntes + 500, (float) $pagamento->financeiro->refresh()->valor_recebido);

        // Corrige o lançamento: valor, forma, data, ID e observação
        $this->actingAs($usuario)->put(
            route('prescricoes.pagamentos.update', [$prescricao, $pagamento]),
            [
                'valor' => '350,00',
                'forma_pagamento' => FormaPagamento::CartaoCredito->value,
                'parcelas' => 3,
                'data_pagamento' => '2026-01-15',
                'identificador' => 'NSU-12345',
                'observacao' => 'Valor corrigido',
            ]
        )->assertRedirect(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro']));

        $pagamento->refresh();

        $this->assertSame(350.0, (float) $pagamento->valor);
        $this->assertSame(FormaPagamento::CartaoCredito, $pagamento->forma_pagamento);
        $this->assertSame(3, (int) $pagamento->parcelas);
        $this->assertSame('2026-01-15', $pagamento->data_pagamento->toDateString());
        $this->assertSame('NSU-12345', $pagamento->identificador);
        $this->assertSame('Valor corrigido', $pagamento->observacao);

        // Quem registrou continua o mesmo
        $this->assertSame($usuario->id, $pagamento->user_id);

        // O recebido acompanha e as parcelas voltam a fechar com ele
        // (o que passar do total das parcelas fica como crédito não alocado)
        $financeiro = $prescricao->financeiro->refresh();
        $esperado = $recebidoAntes + 350;
        $totalParcelas = (float) $financeiro->parcelas()->sum('valor');

        $this->assertSame($esperado, (float) $financeiro->valor_recebido);
        $this->assertSame(
            round(min($esperado, $totalParcelas), 2),
            round((float) $financeiro->parcelas()->sum('valor_pago'), 2)
        );

        // Histórico: pagamento alterado com o que mudou
        $log = PrescricaoLog::where('prescricao_id', $prescricao->id)
            ->where('acao', TipoLogPrescricao::PagamentoEditado->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'A alteração do pagamento deveria ficar no histórico.');

        $campos = collect($log->alteracoes)->pluck('campo')->all();

        foreach (['Valor', 'Forma de pagamento', 'Data do pagamento', 'ID da transação', 'Observação'] as $campo) {
            $this->assertContains($campo, $campos);
        }

        $valor = collect($log->alteracoes)->firstWhere('campo', 'Valor');
        $this->assertSame('R$ 500,00', $valor['de']);
        $this->assertSame('R$ 350,00', $valor['para']);

        // A tela mostra o pagamento alterado
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'financeiro']))
            ->assertOk()
            ->assertSee('Editar pagamento')
            ->assertSee('NSU-12345')
            ->assertSee('Pagamento alterado');
    }
    public function test_valor_invalido_nao_grava(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoComParcelas();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição com parcelas na base.');
        }

        $this->actingAs($usuario);

        $pagamento = $this->lancarPagamento($prescricao, 200);

        $this->actingAs($usuario)
            ->put(route('prescricoes.pagamentos.update', [$prescricao, $pagamento]), [
                'valor' => '0,00',
                'forma_pagamento' => FormaPagamento::Pix->value,
                'data_pagamento' => now()->toDateString(),
            ])
            ->assertSessionHasErrors();

        $this->assertSame(200.0, (float) $pagamento->refresh()->valor);
    }

    public function test_pagamento_de_outra_prescricao_da_404(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoComParcelas();
        $outra = Prescricao::where('id', '!=', $prescricao?->id)->first();

        if (! $usuario || ! $prescricao || ! $outra) {
            $this->markTestSkipped('Sem prescrições suficientes na base.');
        }

        $this->actingAs($usuario);

        $pagamento = $this->lancarPagamento($prescricao, 100);

        $this->actingAs($usuario)
            ->put(route('prescricoes.pagamentos.update', [$outra, $pagamento]), [
                'valor' => '150,00',
                'forma_pagamento' => FormaPagamento::Pix->value,
                'data_pagamento' => now()->toDateString(),
            ])
            ->assertNotFound();

        $this->assertSame(100.0, (float) $pagamento->refresh()->valor);
    }

    public function test_relatorio_de_recebimentos_traz_medico_e_atendimento(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoComParcelas();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição com parcelas na base.');
        }

        $this->actingAs($usuario);

        // Um recebimento de hoje, para aparecer no período do relatório
        $this->lancarPagamento($prescricao, 400);

        $pagina = $this->actingAs($usuario)->get(route('relatorios.recebimentos'));

        $pagina->assertOk()
            ->assertSee('Médico')
            ->assertSee('Atendimento')
            ->assertSee($prescricao->medico_nome ?? '—')
            ->assertSee($prescricao->tipo_atendimento?->label() ?? '—');
    }
}
