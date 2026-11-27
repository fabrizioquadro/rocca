<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoAtendimento;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoSemana;
use App\Models\User;
use App\Services\PrescricaoEdicaoService;
use App\Services\PrescricaoSemanaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Valor do item na edição: o preço negociado não pode voltar para o valor de
 * tabela quando o formulário não devolve o campo `valor` do item.
 */
class ValorDoItemNaEdicaoTest extends TestCase
{
    use DatabaseTransactions;

    private const NEGOCIADO = 123.45;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    private function medicamento(): ?Medicamento
    {
        return Medicamento::where('valor_venda', '>', 0)->orderByDesc('id')->first();
    }

    /**
     * Prescrição com uma semana aberta e um item com preço negociado
     * (diferente do valor de venda do cadastro).
     *
     * @return array{usuario: User, prescricao: Prescricao, semana: PrescricaoSemana, medicamento: Medicamento}
     */
    private function cenario(): array
    {
        $usuario = $this->usuario();
        $paciente = Paciente::orderBy('id')->first();
        $clinica = Clinica::orderBy('id')->first();
        $medicamento = $this->medicamento();

        if (! $usuario || ! $paciente || ! $clinica || ! $medicamento) {
            $this->markTestSkipped('Sem usuário, paciente, clínica ou medicamento na base.');
        }

        $prescricao = Prescricao::create([
            'paciente_id' => $paciente->id,
            'medico_nome' => 'Dr. Teste do Valor',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::ConsultaTratamento,
            'user_id' => $usuario->id,
        ]);

        $semana = $prescricao->semanas()->create([
            'numero' => 1,
            'data_prevista' => now()->addWeek()->startOfDay()->toDateString(),
            'sem_aplicacao' => false,
            'status' => StatusSemana::Agendada,
        ]);

        $semana->itens()->create([
            'tipo' => 'medicamento',
            'medicamento_id' => $medicamento->id,
            'quantidade' => 1,
            'valor' => self::NEGOCIADO,
            'gera_aplicacao' => true,
            'status' => StatusSemanaItem::Aberto,
        ]);

        app(PrescricaoSemanaService::class)->sincronizarFinanceiro($prescricao->fresh());

        return [
            'usuario' => $usuario,
            'prescricao' => $prescricao->fresh(),
            'semana' => $semana->fresh(),
            'medicamento' => $medicamento,
        ];
    }

    public function test_semana_editada_sem_valor_mantem_o_preco_negociado(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semana' => $semana, 'medicamento' => $medicamento] = $this->cenario();

        $this->actingAs($usuario)
            ->put(route('prescricoes.semanas.update', [$prescricao, $semana]), [
                'data_prevista' => $semana->data_prevista?->format('Y-m-d'),
                'itens' => [
                    ['tipo' => 'medicamento', 'medicamento_id' => $medicamento->id, 'quantidade' => 1],
                ],
            ])
            ->assertRedirect();

        $item = $semana->fresh()->itens()->first();

        $this->assertSame(self::NEGOCIADO, (float) $item->valor);
        $this->assertNotSame((float) $medicamento->valor_venda, (float) $item->valor);
    }

    public function test_edicao_completa_sem_valor_mantem_o_preco_negociado(): void
    {
        ['prescricao' => $prescricao, 'semana' => $semana, 'medicamento' => $medicamento] = $this->cenario();

        $resultado = app(PrescricaoEdicaoService::class)->montarPlano($prescricao, [
            [
                'id' => $semana->id,
                'data_prevista' => $semana->data_prevista?->format('Y-m-d'),
                'sem_aplicacao' => '0',
                'itens' => [
                    'i1' => ['tipo' => 'medicamento', 'medicamento_id' => $medicamento->id, 'quantidade' => 1],
                ],
            ],
        ]);

        $this->assertSame([], $resultado['problemas']);

        $linha = collect($resultado['plano'])->firstWhere('semana.id', $semana->id);

        $this->assertSame(PrescricaoEdicaoService::MANTER, $linha['acao']);
        $this->assertSame(self::NEGOCIADO, (float) $linha['itens'][0]['valor']);
    }

    public function test_item_novo_sem_valor_usa_o_valor_de_venda(): void
    {
        ['medicamento' => $medicamento] = $this->cenario();

        $itens = app(PrescricaoSemanaService::class)->prepararItens([
            ['tipo' => 'medicamento', 'medicamento_id' => $medicamento->id, 'quantidade' => 1, 'valor' => ''],
        ]);

        $this->assertSame((float) $medicamento->valor_venda, (float) $itens['itens'][0]['valor']);
    }

    public function test_conferencia_devolve_as_semanas_com_os_itens_na_tela(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semana' => $semana, 'medicamento' => $medicamento] = $this->cenario();

        $pagina = $this->actingAs($usuario)
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'medico_nome' => $prescricao->medico_nome,
                'semanas' => [
                    $semana->id => [
                        'id' => $semana->id,
                        'sem_aplicacao' => '0',
                        'data_prevista' => $semana->data_prevista?->format('Y-m-d'),
                        'itens' => [
                            'i1' => [
                                'tipo' => 'medicamento',
                                'medicamento_id' => $medicamento->id,
                                'quantidade' => 1,
                                'valor' => number_format(self::NEGOCIADO, 2, ',', '.'),
                            ],
                        ],
                    ],
                ],
            ]);

        $pagina->assertOk();

        $html = $pagina->getContent();

        // Itens como lista (array) e não como objeto: era o que deixava todas
        // as semanas sem medicamento na tela de conferência
        $this->assertMatchesRegularExpression('/"itens":\[\{/', $html);
        $this->assertDoesNotMatchRegularExpression('/"itens":\{"i1"/', $html);
    }
}
