<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAtendimento;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SemanasDataAplicacaoTest extends TestCase
{
    use DatabaseTransactions;

    public function test_listagem_de_semanas_mostra_a_data_de_aplicacao(): void
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first();

        $prescricao = Prescricao::with('semanas.itens')
            ->orderByDesc('id')
            ->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->semanas
                ->contains(fn (PrescricaoSemana $semana) => $semana->itens->isNotEmpty()));

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem administrador ou prescrição com itens na base.');
        }

        $semana = $prescricao->semanas
            ->first(fn (PrescricaoSemana $semana) => $semana->itens->isNotEmpty());

        // Aplicação em dois dias: o paciente voltou para completar
        $atendimento = PrescricaoSemanaAtendimento::create([
            'prescricao_semana_id' => $semana->id,
            'chegada_em' => '2026-10-10 09:00:00',
            'iniciado_em' => '2026-10-10 09:10:00',
            'iniciado_por_user_id' => $usuario->id,
            'finalizado_em' => '2026-10-12 14:10:00',
            'finalizado_por_user_id' => $usuario->id,
        ]);

        foreach (['2026-10-10 09:20:00', '2026-10-12 14:00:00'] as $aplicadoEm) {
            $atendimento->aplicacoes()->create([
                'prescricao_semana_item_id' => $semana->itens->first()->id,
                'medicamento_id' => $semana->itens->first()->medicamento_id,
                'quantidade' => 1,
                'aplicado_em' => $aplicadoEm,
                'user_id' => $usuario->id,
            ]);
        }

        $this->actingAs($usuario)
            ->get(route('prescricoes.show', $prescricao))
            ->assertOk()
            ->assertSee('Data prevista')
            ->assertSee('Data de aplicação')
            ->assertSee('<td>10/10/2026 · 12/10/2026</td>', false);
    }
}
