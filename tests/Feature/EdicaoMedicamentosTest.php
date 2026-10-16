<?php

namespace Tests\Feature;

use App\Enums\StatusSemana;
use App\Enums\StatusSemanaItem;
use App\Enums\TipoAtendimento;
use App\Enums\TipoLogPrescricao;
use App\Enums\TipoMedicamento;
use App\Enums\TipoUsuario;
use App\Models\Clinica;
use App\Models\Medicamento;
use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoLog;
use App\Models\PrescricaoSemana;
use App\Models\PrescricaoSemanaAplicacao;
use App\Models\User;
use App\Services\FinanceiroPagamentoService;
use App\Services\PrescricaoSemanaService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Edição completa dos medicamentos e semanas da prescrição: a tela monta as
 * semanas, o "Conferir alterações" mostra o que vai mudar (com o efeito no
 * financeiro) e só depois de autorizar as alterações são gravadas.
 *
 * As semanas com aplicação registrada não entram: elas ficam travadas.
 */
class EdicaoMedicamentosTest extends TestCase
{
    use DatabaseTransactions;

    private function usuario(): ?User
    {
        return User::where('tipo', TipoUsuario::Administrador->value)->first() ?? User::orderBy('id')->first();
    }

    private function medicamento(): ?Medicamento
    {
        return Medicamento::where('valor_venda', '>', 0)->orderByDesc('id')->first()
            ?? Medicamento::orderByDesc('id')->first();
    }

    /**
     * Prescrição de teste com 3 semanas, um item por semana e o financeiro já
     * gerado (as parcelas saem do próprio serviço da aplicação).
     *
     * @return array{usuario: User, prescricao: Prescricao, semanas: array<int, PrescricaoSemana>}
     */
    private function cenario(int $quantidade = 2, float $valor = 100.0): array
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
            'medico_nome' => 'Dr. Teste da Edição',
            'clinica_id' => $clinica->id,
            'tipo_atendimento' => TipoAtendimento::ConsultaTratamento,
            'user_id' => $usuario->id,
        ]);

        $semanas = [];

        foreach ([1, 2, 3] as $numero) {
            $semana = $prescricao->semanas()->create([
                'numero' => $numero,
                'data_prevista' => now()->addWeeks($numero)->startOfDay()->toDateString(),
                'sem_aplicacao' => false,
                'status' => StatusSemana::Agendada,
            ]);

            $semana->itens()->create([
                'tipo' => 'medicamento',
                'medicamento_id' => $medicamento->id,
                'quantidade' => $quantidade,
                'valor' => $valor,
                'gera_aplicacao' => true,
                'status' => StatusSemanaItem::Aberto,
            ]);

            $semanas[] = $semana;
        }

        $this->servico()->sincronizarFinanceiro($prescricao->fresh());

        return ['usuario' => $usuario, 'prescricao' => $prescricao->fresh(), 'semanas' => $semanas];
    }

    private function servico(): PrescricaoSemanaService
    {
        return app(PrescricaoSemanaService::class);
    }

    /**
     * Linha de semana no formato que o formulário envia.
     *
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<string, mixed>
     */
    private function linha(?PrescricaoSemana $semana, array $itens, array $extra = []): array
    {
        return array_merge([
            'id' => $semana?->id,
            'data_prevista' => $semana?->data_prevista?->format('Y-m-d'),
            'observacao' => $semana?->observacao,
            'itens' => $itens,
        ], $extra);
    }

    /**
     * Item no formato que o formulário envia.
     *
     * @return array<string, mixed>
     */
    private function item($medicamentoId, float $quantidade, float $valor): array
    {
        return [
            'tipo' => 'medicamento',
            'medicamento_id' => $medicamentoId,
            'quantidade' => $quantidade,
            'valor' => $valor,
        ];
    }

    public function test_tela_de_edicao_tem_tudo_na_mesma_pagina(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $pagina = $this->actingAs($usuario)->get(route('prescricoes.edit', $prescricao));

        $pagina->assertOk()
            ->assertSee('Editar prescrição #'.$prescricao->id)
            // Cabeçalho, anexos, medicamentos e semanas na MESMA tela
            ->assertSee('name="medico_id"', false)
            ->assertSee('name="anexos[]"', false)
            ->assertSee('Medicamentos e semanas')
            ->assertSee('Adicionar semana')
            ->assertSee('Conferir alterações')
            ->assertSee(route('prescricoes.update', $prescricao));

        // As semanas vão para a tela e nenhuma está travada
        $this->assertStringContainsString('window.prescricaoSemanas = [', $pagina->getContent());
        $this->assertStringContainsString('"bloqueada":false', $pagina->getContent());

        // O resumo do que vai mudar também sai nesta página (e não em outra)
        $this->assertStringNotContainsString('prescricoes.medicamentos', $pagina->getContent());
    }

    public function test_previa_mostra_o_que_vai_mudar_e_nao_grava_nada(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $medicamento = $this->medicamento();
        [$primeira, $segunda, $terceira] = $semanas;

        $pagina = $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), array_merge([
            'clinica_id' => $prescricao->clinica_id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
        ], ['semanas' => [
            'sem'.$primeira->id => $this->linha($primeira, [
                $this->item($medicamento->id, 3, 100.0),   // 2 -> 3
            ]),
            'sem'.$segunda->id => $this->linha($segunda, [
                $this->item($medicamento->id, 2, 100.0),
            ]),
            'sem'.$terceira->id => $this->linha($terceira, [], ['excluir' => 1]),
            'novo_1' => $this->linha(null, [
                $this->item($medicamento->id, 1, 150.0),
            ], ['data_prevista' => now()->addWeeks(4)->toDateString()]),
        ]]));

        $pagina->assertOk()
            // O resumo aparece na MESMA página do formulário
            ->assertSee('Editar prescrição #'.$prescricao->id)
            ->assertSee('Medicamentos e semanas')
            ->assertSee('Resumo das alterações')
            ->assertSee('O que vai mudar nas semanas')
            ->assertSee('Efeito no financeiro')
            ->assertSee('Nova')
            ->assertSee('Alterada')
            ->assertSee('Excluída')
            ->assertSee('Autorizar e aplicar')
            // A projeção do financeiro é feita por simulação: 3x100 + 2x100 + 1x150 = 650
            ->assertSee('R$ 650,00')
            ->assertSee('R$ 600,00');

        // Nada foi gravado
        $primeira->refresh();

        $this->assertSame(2.0, (float) $primeira->itens()->first()->quantidade, 'A prévia não deve gravar.');
        $this->assertNotNull($terceira->fresh(), 'A semana excluída só sai depois de autorizar.');
        $this->assertSame(3, $prescricao->semanas()->count());
    }

    public function test_autorizar_aplica_criar_alterar_e_excluir(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $medicamento = $this->medicamento();
        [$primeira, $segunda, $terceira] = $semanas;
        $idExcluida = $terceira->id;

        $payload = [
            'clinica_id' => $prescricao->clinica_id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            'semanas' => [
                // As chaves dos itens são como o formulário manda (i1, i2...)
                'sem'.$primeira->id => $this->linha($primeira, ['i1' => $this->item($medicamento->id, 3, 100.0)]),
                'sem'.$segunda->id => $this->linha($segunda, ['i1' => $this->item($medicamento->id, 2, 100.0)]),
                'sem'.$terceira->id => $this->linha($terceira, [], ['excluir' => 1]),
                'novo_1' => $this->linha(null, [
                    'i1' => $this->item($medicamento->id, 1, 150.0),
                ], ['data_prevista' => now()->addWeeks(4)->toDateString()]),
            ],
        ];

        $this->actingAs($usuario)
            ->put(route('prescricoes.update', $prescricao), $payload)
            ->assertOk();

        $this->actingAs($usuario)
            ->post(route('prescricoes.editar.aplicar', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao))
            ->assertSessionHas('success');

        $prescricao->refresh();

        // Criou, alterou e excluiu
        $this->assertSame(3, $prescricao->semanas()->count());
        $this->assertNull(PrescricaoSemana::find($idExcluida), 'A semana excluída deveria sair.');
        $this->assertSame(3.0, (float) $primeira->fresh()->itens()->first()->quantidade);

        // Renumerou 1..N na ordem da tela
        $this->assertSame(
            [1, 2, 3],
            $prescricao->semanas()->orderBy('numero')->pluck('numero')->all()
        );

        // A semana nova entrou com o item e o valor informados
        // (a relação já vem ordenada por número: a última é a nova)
        $nova = $prescricao->semanas()->get()->last();

        $this->assertSame(1.0, (float) $nova->itens()->first()->quantidade);
        $this->assertSame(150.0, (float) $nova->itens()->first()->valor);
        $this->assertNotContains($nova->id, [$primeira->id, $segunda->id], 'A última semana deveria ser a nova.');

        // Histórico: o que mudou fica registrado
        foreach ([
            TipoLogPrescricao::SemanaEditada,
            TipoLogPrescricao::SemanaCriada,
            TipoLogPrescricao::SemanaExcluida,
            TipoLogPrescricao::Edicao,
        ] as $acao) {
            $this->assertTrue(
                PrescricaoLog::where('prescricao_id', $prescricao->id)->where('acao', $acao->value)->exists(),
                'Faltou o registro de '.$acao->label().' no histórico.'
            );
        }

        // Financeiro acompanhou: 3 semanas que cobram (3x100 + 2x100 + 1x150 = 650)
        $financeiro = $prescricao->financeiro()->first();

        $this->assertNotNull($financeiro);
        $this->assertSame(650.0, (float) $financeiro->valor_bruto);
        $this->assertSame(650.0, (float) $financeiro->valor_total);
        $this->assertSame(3, $financeiro->parcelas()->count());
    }

    public function test_semana_com_aplicacao_fica_travada_e_nao_pode_ser_alterada(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $medicamento = $this->medicamento();
        [$primeira, $segunda] = $semanas;

        $this->registrarAplicacao($segunda, 2.0);

        // A tela mostra a semana travada
        $this->actingAs($usuario)
            ->get(route('prescricoes.edit', $prescricao))
            ->assertOk()
            ->assertSee('"bloqueada":true', false);

        // Tentar alterar a semana aplicada é recusado
        $resposta = $this->actingAs($usuario)
            ->from(route('prescricoes.edit', $prescricao))
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'semanas' => [
                    'sem'.$primeira->id => $this->linha($primeira, [$this->item($medicamento->id, 2, 100.0)]),
                    'sem'.$segunda->id => $this->linha($segunda, [$this->item($medicamento->id, 5, 100.0)]),
                ],
            ]);

        $resposta->assertRedirect(route('prescricoes.edit', $prescricao))
            ->assertSessionHasErrors('semanas');

        $this->assertSame(2.0, (float) $segunda->fresh()->itens()->first()->quantidade);
    }

    public function test_semana_com_aplicacao_de_ampola_nao_pode_ser_excluida(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $medicamento = $this->medicamento();
        [$primeira, $segunda] = $semanas;

        $this->registrarAplicacao($segunda, 2.0, TipoMedicamento::Ampola);

        $this->actingAs($usuario)
            ->from(route('prescricoes.edit', $prescricao))
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'semanas' => [
                    'sem'.$primeira->id => $this->linha($primeira, [$this->item($medicamento->id, 2, 100.0)]),
                    'sem'.$segunda->id => $this->linha($segunda, [], ['excluir' => 1]),
                ],
            ])
            ->assertSessionHasErrors('semanas');

        $this->assertNotNull($segunda->fresh(), 'A semana com aplicação de ampola não pode ser excluída.');
    }

    public function test_nao_deixa_a_prescricao_sem_cobranca_quando_ja_recebeu(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $this->registrarPagamento($prescricao, 600.0);

        $this->actingAs($usuario)
            ->from(route('prescricoes.edit', $prescricao))
            ->put(route('prescricoes.update', $prescricao), [
                'clinica_id' => $prescricao->clinica_id,
                'tipo_atendimento' => $prescricao->tipo_atendimento->value,
                'semanas' => collect($semanas)->map(fn (PrescricaoSemana $semana) => $this->linha($semana, [], ['sem_aplicacao' => 1]))
                    ->values()
                    ->all(),
            ])
            ->assertSessionHasErrors('semanas');

        $this->assertSame(3, $prescricao->semanas()->count());
    }

    public function test_o_dinheiro_ja_recebido_continua_no_financeiro_depois_da_alteracao(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao, 'semanas' => $semanas] = $this->cenario();

        $medicamento = $this->medicamento();
        [$primeira, $segunda, $terceira] = $semanas;

        // 3 semanas x 2 x R$ 100 = R$ 600, recebidos por inteiro
        $this->registrarPagamento($prescricao, 600.0);

        $this->actingAs($usuario)->put(route('prescricoes.update', $prescricao), [
            'clinica_id' => $prescricao->clinica_id,
            'tipo_atendimento' => $prescricao->tipo_atendimento->value,
            'semanas' => [
                'sem'.$primeira->id => $this->linha($primeira, [$this->item($medicamento->id, 2, 100.0)]),
                'sem'.$segunda->id => $this->linha($segunda, [$this->item($medicamento->id, 2, 100.0)]),
                'sem'.$terceira->id => $this->linha($terceira, [], ['excluir' => 1]),
            ],
        ])->assertOk();

        $this->actingAs($usuario)
            ->post(route('prescricoes.editar.aplicar', $prescricao))
            ->assertRedirect(route('prescricoes.show', $prescricao));

        $financeiro = $prescricao->refresh()->financeiro()->first();

        // O total caiu para R$ 400 e os R$ 600 recebidos continuam registrados:
        // R$ 400 nas parcelas e R$ 200 de crédito a favor do paciente
        $this->assertSame(400.0, (float) $financeiro->valor_total);
        $this->assertSame(600.0, $financeiro->valor_recebido);
        $this->assertSame(400.0, $financeiro->valor_pago);
        $this->assertSame(200.0, $financeiro->valor_nao_alocado);
        $this->assertSame(2, $financeiro->parcelas()->count());
    }

    public function test_aplicar_sem_conferir_nao_altera_nada(): void
    {
        ['usuario' => $usuario, 'prescricao' => $prescricao] = $this->cenario();

        $this->actingAs($usuario)
            ->post(route('prescricoes.editar.aplicar', $prescricao))
            ->assertRedirect(route('prescricoes.edit', $prescricao))
            ->assertSessionHas('error');

        $this->assertSame(3, $prescricao->semanas()->count());
    }

    // --- Apoio -------------------------------------------------------------

    /**
     * Registra uma aplicação na semana: é o que trava a edição e a exclusão.
     */
    private function registrarAplicacao(PrescricaoSemana $semana, float $quantidade, ?TipoMedicamento $tipo = null): void
    {
        if ($tipo) {
            $medicamentoDoTipo = Medicamento::where('tipo', $tipo->value)->value('id');

            if (! $medicamentoDoTipo) {
                $this->markTestSkipped('Sem medicamento do tipo '.$tipo->value.' na base.');
            }

            $semana->itens()->first()->update(['medicamento_id' => $medicamentoDoTipo]);
            $semana->unsetRelation('itens');
        }

        $atendimento = $semana->atendimentos()->create([
            'iniciado_em' => now()->subHour(),
            'iniciado_por_user_id' => $this->usuario()?->id,
            'finalizado_em' => now(),
            'finalizado_por_user_id' => $this->usuario()?->id,
        ]);

        PrescricaoSemanaAplicacao::create([
            'prescricao_semana_atendimento_id' => $atendimento->id,
            'prescricao_semana_item_id' => $semana->itens()->first()->id,
            'medicamento_id' => $semana->itens()->first()->medicamento_id,
            'quantidade' => $quantidade,
            'aplicado_em' => now(),
            'user_id' => $this->usuario()?->id,
        ]);

        $semana->update(['status' => StatusSemana::Aplicada]);
        $semana->refresh();
    }

    /**
     * Lança um recebimento no financeiro (o serviço redistribui nas parcelas).
     */
    private function registrarPagamento(Prescricao $prescricao, float $valor): void
    {
        $financeiro = $prescricao->financeiro()->first();

        $financeiro->pagamentos()->create([
            'valor' => $valor,
            'data_pagamento' => now()->toDateString(),
            'forma_pagamento' => 'dinheiro',
            'user_id' => $this->usuario()?->id,
        ]);

        app(FinanceiroPagamentoService::class)->reprocessar($financeiro->refresh());
    }
}
