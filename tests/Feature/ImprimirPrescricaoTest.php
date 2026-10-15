<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * "Imprimir Prescrição": o mesmo documento do cadastro, sem os valores
 * (colunas de valor, total da semana, bloco financeiro e valores embutidos no
 * histórico), para imprimir ou enviar ao paciente.
 */
class ImprimirPrescricaoTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    /**
     * Prescrição com semanas e, se possível, com financeiro e aplicações.
     */
    private function prescricaoCompleta(): ?Prescricao
    {
        return Prescricao::withCount('semanas')->get()
            ->sortByDesc(fn (Prescricao $prescricao) => ($prescricao->financeiro()->exists() ? 100 : 0)
                + ($prescricao->semanas()->whereHas('atendimentos')->exists() ? 50 : 0)
                + $prescricao->semanas_count)
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas_count > 0);
    }

    /**
     * @return array{0: User, 1: Prescricao}
     */
    private function cenario(): array
    {
        $usuario = $this->usuario();
        $prescricao = $this->prescricaoCompleta();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição com semanas na base.');
        }

        return [$usuario, $prescricao];
    }

    public function test_pagina_do_paciente_sai_sem_valores(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.imprimir.prescricao', $prescricao));

        $pagina->assertOk();

        // O documento em si continua lá
        $pagina->assertSee('Paciente')
            ->assertSee($prescricao->paciente?->nome ?? '—')
            ->assertSee('Semana 1/')
            ->assertSee('Imprimir')
            ->assertSee(route('prescricoes.imprimir.prescricao.pdf', $prescricao));

        // Sem os valores: colunas, total da semana e o bloco financeiro
        $pagina->assertDontSee('Valor unitário')
            ->assertDontSee('Valor total')
            ->assertDontSee('Total da semana')
            ->assertDontSee('Valor bruto')
            ->assertDontSee('Quitado');
    }

    public function test_pagina_do_cadastro_continua_com_os_valores(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $this->actingAs($usuario)
            ->get(route('prescricoes.imprimir', $prescricao))
            ->assertOk()
            ->assertSee('Valor unit.')
            ->assertSee('Financeiro');
    }

    public function test_pdf_do_paciente_sai_sem_valores(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $resposta = $this->actingAs($usuario)->get(route('prescricoes.imprimir.prescricao.pdf', $prescricao));

        $resposta->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringContainsString(
            'prescricao-paciente-'.$prescricao->id,
            (string) $resposta->headers->get('content-disposition')
        );

        $conteudo = $resposta->getContent();

        $this->assertStringStartsWith('%PDF', $conteudo);
        $this->assertGreaterThan(2000, strlen($conteudo));
    }

    public function test_pdf_do_cadastro_continua_igual(): void
    {
        [$usuario, $prescricao] = $this->cenario();

        $resposta = $this->actingAs($usuario)->get(route('prescricoes.imprimir.pdf', $prescricao));

        $resposta->assertOk();
        $this->assertSame('application/pdf', $resposta->headers->get('content-type'));
        $this->assertStringStartsWith('%PDF', $resposta->getContent());
    }
}
