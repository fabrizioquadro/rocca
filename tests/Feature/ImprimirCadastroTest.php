<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\User;
use Tests\TestCase;

/**
 * "Imprimir cadastro": prescrição detalhada em uma página, com opção de
 * editar os dados e de gerar o PDF.
 */
class ImprimirCadastroTest extends TestCase
{
    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    private function prescricaoCompleta(): ?Prescricao
    {
        return Prescricao::with('financeiro')->get()
            ->sortByDesc(fn (Prescricao $prescricao) => ($prescricao->financeiro ? 1 : 0))
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas()->exists());
    }

    public function test_pagina_de_impressao_traz_a_prescricao_detalhada(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.imprimir', $prescricao));

        $pagina->assertOk()
            ->assertSee('Imprimir cadastro')
            ->assertSee('Prescrição #'.$prescricao->id)
            ->assertSee($prescricao->paciente?->nome ?? '—')
            ->assertSee('Semana 1/')
            ->assertSee('Anotações / textos')
            ->assertSee('Documento gerado pelo sistema');

        // As ações da tela: editar os dados, gerar PDF e imprimir
        $pagina->assertSee('Editar dados')
            ->assertSee('Gerar PDF')
            ->assertSee('Imprimir')
            ->assertSee(route('prescricoes.edit', $prescricao))
            ->assertSee(route('prescricoes.imprimir.pdf', $prescricao));

        // Botão que abre a tela, na prescrição e na listagem
        $this->actingAs($usuario)
            ->get(route('prescricoes.show', $prescricao))
            ->assertOk()
            ->assertSee('Imprimir Cadastro')
            ->assertSee(route('prescricoes.imprimir', $prescricao));

        $this->actingAs($usuario)
            ->get(route('prescricoes.index'))
            ->assertOk()
            ->assertSee(route('prescricoes.imprimir', $prescricao));
    }

    public function test_pagina_mostra_o_financeiro_quando_existe(): void
    {
        $usuario = $this->usuario();

        $prescricao = Prescricao::with(['financeiro.parcelas', 'financeiro.pagamentos'])->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->financeiro !== null
                && $prescricao->financeiro->parcelas->isNotEmpty());

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem prescrição com financeiro na base.');
        }

        $financeiro = $prescricao->financeiro;

        $this->actingAs($usuario)
            ->get(route('prescricoes.imprimir', $prescricao))
            ->assertOk()
            ->assertSee('Financeiro')
            ->assertSee($financeiro->valor_bruto_formatado)
            ->assertSee($financeiro->valor_total_formatado)
            ->assertSee($financeiro->valor_aberto_formatado)
            ->assertSee('Parcela');
    }

    public function test_gera_o_pdf_para_download(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $resposta = $this->actingAs($usuario)->get(route('prescricoes.imprimir.pdf', $prescricao));

        $resposta->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString('prescricao-'.$prescricao->id, (string) $resposta->headers->get('content-disposition'));

        // O arquivo é um PDF de verdade (assinatura %PDF) e tem conteúdo
        $conteudo = $resposta->getContent();

        $this->assertStringStartsWith('%PDF', $conteudo);
        $this->assertGreaterThan(2000, strlen($conteudo));
    }

    public function test_anotacao_pode_ser_inserida_pela_tela_de_impressao(): void
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição na base.');
        }

        $this->actingAs($usuario)
            ->get(route('prescricoes.imprimir', $prescricao))
            ->assertOk()
            ->assertSee('Inserir anotação')
            ->assertSee(route('prescricoes.observacoes.store', $prescricao));
    }
}
