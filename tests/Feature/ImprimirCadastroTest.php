<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Página "Imprimir cadastro": a prescrição inteira em uma página, em cards
 * (dados, financeiro, semanas com as aplicações, anotações e histórico), com
 * as edições em modal e o PDF para download.
 */
class ImprimirCadastroTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    /**
     * Prescrição com financeiro e/ou aplicação: é o caso que o cliente usa.
     */
    private function prescricaoCompleta(): ?Prescricao
    {
        return Prescricao::withCount('semanas')->get()
            ->sortByDesc(fn (Prescricao $prescricao) => ($prescricao->financeiro()->exists() ? 100 : 0)
                + ($prescricao->semanas()->whereHas('atendimentos')->exists() ? 50 : 0)
                + $prescricao->semanas_count)
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas_count > 0);
    }

    public function test_pagina_traz_os_cards_com_tudo(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.imprimir', $prescricao));

        $pagina->assertOk();

        // Os cards da página, na ordem pedida pelo cliente
        $pagina->assertSee('Dados da prescrição')
            ->assertSee('Financeiro')
            ->assertSee('Semanas e aplicações')
            ->assertSee('Anotações / textos')
            ->assertSee('Histórico da prescrição');

        // Dados da prescrição
        $pagina->assertSee($prescricao->paciente?->nome ?? '—')
            ->assertSee('Semana 1/');

        // Ações da página
        $pagina->assertSee('Gerar PDF')
            ->assertSee('Imprimir')
            ->assertSee(route('prescricoes.imprimir.pdf', $prescricao));
    }

    public function test_dados_e_pagamento_sao_editados_em_modal(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.imprimir', $prescricao));

        $pagina->assertOk()
            ->assertSee('Editar dados')
            ->assertSee('modal-editar-prescricao', false)
            ->assertSee(route('prescricoes.update', $prescricao));

        // Os campos do modal de dados
        foreach (['medico_id', 'medico_nome', 'clinica_id', 'tipo_atendimento', 'agendamento', 'observacoes'] as $campo) {
            $pagina->assertSee('name="'.$campo.'"', false);
        }

        $pagamento = $prescricao->financeiro?->pagamentos->first();

        if ($pagamento) {
            $pagina->assertSee('modal-editar-pagamento-'.$pagamento->id, false)
                ->assertSee(route('prescricoes.pagamentos.update', [$prescricao, $pagamento]));

            // Os formulários da página voltam para ela depois de salvar
            $pagina->assertSee('name="origem" value="imprimir"', false);
        }
    }

    public function test_mostra_quem_aplicou_lote_e_codigo(): void
    {
        $usuario = $this->usuario();

        $prescricao = Prescricao::get()
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas()
                ->whereHas('atendimentos.aplicacoes')
                ->exists());

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem prescrição com aplicação registrada na base.');
        }

        $aplicacao = $prescricao->semanas()
            ->with(['atendimentos.aplicacoes.user', 'atendimentos.aplicacoes.medicamento'])
            ->get()
            ->flatMap(fn ($semana) => $semana->atendimentos->flatMap->aplicacoes)
            ->first();

        $this->assertNotNull($aplicacao, 'A prescrição deveria ter aplicação.');

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.imprimir', $prescricao));

        $pagina->assertOk()
            ->assertSee('Aplicado em')
            ->assertSee('Quem aplicou')
            ->assertSee('Lote')
            ->assertSee('Código de barras');

        if ($aplicacao->user) {
            $pagina->assertSee($aplicacao->user->nome);
        }

        if ($aplicacao->lote) {
            $pagina->assertSee($aplicacao->lote);
        }
    }

    public function test_mostra_os_logs_no_fim_da_pagina(): void
    {
        $usuario = $this->usuario();

        $prescricao = PrescricaoLog::orderByDesc('id')->first()?->prescricao;

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem prescrição com histórico na base.');
        }

        $log = PrescricaoLog::where('prescricao_id', $prescricao->id)->latest('id')->first();

        $this->actingAs($usuario)
            ->get(route('prescricoes.imprimir', $prescricao))
            ->assertOk()
            ->assertSee('Histórico da prescrição')
            ->assertSee($log->acao->label())
            ->assertSee($log->descricao);
    }

    public function test_gera_o_pdf_completo_para_download(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $resposta = $this->actingAs($usuario)->get(route('prescricoes.imprimir.pdf', $prescricao));

        $resposta->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString(
            'prescricao-'.$prescricao->id,
            (string) $resposta->headers->get('content-disposition')
        );

        // PDF de verdade (assinatura %PDF) e com conteúdo
        $conteudo = $resposta->getContent();

        $this->assertStringStartsWith('%PDF', $conteudo);
        $this->assertGreaterThan(2000, strlen($conteudo));
    }

    public function test_lista_de_medicos_para_o_modal(): void
    {
        $usuario = $this->usuario();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário na base.');
        }

        // Vem da Feegow: se ela estiver fora, volta lista vazia (e o modal
        // mantém o médico atual)
        $this->actingAs($usuario)
            ->getJson(route('prescricoes.medicos'))
            ->assertOk()
            ->assertJsonStructure(['medicos']);
    }

    public function test_editar_pela_pagina_volta_para_ela(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        // Com origem=imprimir (modal da página) volta para a página
        $this->actingAs($usuario)
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'medico_nome' => $prescricao->medico_nome,
                'origem' => 'imprimir',
            ])
            ->assertRedirect(route('prescricoes.imprimir', $prescricao));

        // Sem origem, continua voltando para a tela da prescrição
        $this->actingAs($usuario)
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            ])
            ->assertRedirect(route('prescricoes.show', $prescricao));
    }
}
