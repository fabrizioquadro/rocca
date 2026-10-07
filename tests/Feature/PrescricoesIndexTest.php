<?php

namespace Tests\Feature;

use App\Enums\TipoUsuario;
use App\Models\Prescricao;
use App\Models\User;
use Tests\TestCase;

class PrescricoesIndexTest extends TestCase
{
    public function test_listagem_mostra_a_data_da_proxima_aplicacao(): void
    {
        $usuario = User::where('tipo', TipoUsuario::Administrador->value)->first();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário administrador.');
        }

        $prescricao = Prescricao::with('semanas')->get()
            ->first(fn (Prescricao $prescricao) => $prescricao->proxima_aplicacao !== null);

        $resposta = $this->actingAs($usuario)
            ->get(route('prescricoes.index'))
            ->assertOk()
            ->assertSee('Data Aplicação')
            ->assertDontSee('<th>Agendamento</th>', false);

        if (! $prescricao) {
            $this->markTestSkipped('Sem prescrição com semana agendada na base.');
        }

        // A data aparece como texto (d/m/Y) e como valor de ordenação (Y-m-d)
        $resposta
            ->assertSee($prescricao->proxima_aplicacao_formatada)
            ->assertSee('data-order="'.$prescricao->proxima_aplicacao->toDateString().'"', false);
    }
}
