<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Prescricao;
use App\Models\PrescricaoNotaFiscal;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Aba Fiscal: formulário de emissão e serviços da Omie.
 */
class PrescricaoFiscalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.omie.base_url' => 'https://app.omie.com.br/api/v1',
            'services.omie.app_key' => 'chave-de-teste',
            'services.omie.app_secret' => 'segredo-de-teste',
        ]);
    }

    private function usuario(): ?User
    {
        return User::orderBy('id')->first();
    }

    public function test_lista_somente_os_servicos_ativos_da_omie(): void
    {
        Http::fake([
            'app.omie.com.br/*' => Http::response([
                'cadastros' => [
                    [
                        'intListar' => ['nCodServ' => 10],
                        'cabecalho' => ['cCodigo' => '001', 'cDescricao' => 'Bioimpedância', 'nPrecoUnit' => 150.5],
                        'info' => ['inativo' => 'N'],
                    ],
                    [
                        'intListar' => ['nCodServ' => 11],
                        'cabecalho' => ['cCodigo' => '002', 'cDescricao' => 'Serviço antigo', 'nPrecoUnit' => 80],
                        'info' => ['inativo' => 'S'],
                    ],
                ],
            ]),
        ]);

        $usuario = $this->usuario();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário cadastrado.');
        }

        $this->actingAs($usuario)
            ->getJson(route('prescricoes.fiscal.servicos'))
            ->assertOk()
            ->assertJsonCount(1, 'servicos')
            ->assertJsonPath('servicos.0.codigo', 10)
            ->assertJsonPath('servicos.0.codigo_exibicao', '001')
            ->assertJsonPath('servicos.0.descricao', 'Bioimpedância');
    }

    public function test_mostra_o_formulario_de_emissao_quando_o_paciente_esta_integrado(): void
    {
        $usuario = $this->usuario();
        $prescricao = Prescricao::query()->whereNotNull('paciente_id')->first();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição para o teste.');
        }

        $prescricao->paciente->forceFill(['codigo_cliente_omie' => 123456])->save();

        $this->actingAs($usuario)
            ->get(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'fiscal']))
            ->assertOk()
            ->assertSee('Emitir nota fiscal')
            ->assertSee($prescricao->id.'/1')
            ->assertSee('Cidade de prestação')
            ->assertSee('E-mail do paciente');
    }

    public function test_nao_mostra_o_formulario_sem_paciente_integrado(): void
    {
        $usuario = $this->usuario();
        $prescricao = Prescricao::query()->whereNotNull('paciente_id')->first();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição para o teste.');
        }

        $prescricao->paciente->forceFill(['codigo_cliente_omie' => null])->save();

        $this->actingAs($usuario)
            ->get(route('prescricoes.show', ['prescricao' => $prescricao, 'aba' => 'fiscal']))
            ->assertOk()
            ->assertSee('Paciente não integrado com a Omie')
            ->assertDontSee('Emitir nota fiscal');
    }

    public function test_emite_a_nota_faturando_a_ordem_de_servico(): void
    {
        $usuario = $this->usuario();
        $prescricao = Prescricao::query()->whereNotNull('paciente_id')->first();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição para o teste.');
        }

        $prescricao->paciente->forceFill(['codigo_cliente_omie' => 123456])->save();

        config([
            'services.omie.cidade_prestacao' => 'SAO PAULO (SP)',
            'services.omie.categoria' => '1.01.01',
            'services.omie.conta_corrente' => '5311435537',
            'services.omie.condicao_pagamento' => '000',
        ]);

        Http::fake([
            'app.omie.com.br/*' => function ($request) {
                $corpo = json_decode($request->body(), true);

                return match ($corpo['call'] ?? null) {
                    'IncluirOS' => Http::response([
                        'cCodIntOS' => $corpo['param'][0]['Cabecalho']['cCodIntOS'] ?? null,
                        'nCodOS' => 98765,
                        'cDescStatus' => 'Ordem de Serviço adicionada com sucesso!',
                    ]),
                    'FaturarOS' => Http::response([
                        'nCodOS' => 98765,
                        'cDescStatus' => 'Faturamento realizado com sucesso.',
                    ]),
                    default => Http::response([]),
                };
            },
        ]);

        $this->actingAs($usuario)
            ->postJson(route('prescricoes.fiscal.notas.store', $prescricao), [
                'n_cod_servico' => 5311624010,
                'quantidade' => '1',
                'valor_unitario' => '600,00',
                'descricao' => 'Consulta médica - Dr. Túlio',
                'email' => 'paciente@exemplo.com',
            ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('nota.n_cod_os', 98765);

        $nota = PrescricaoNotaFiscal::query()
            ->where('prescricao_id', $prescricao->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($nota);
        $this->assertSame('faturada', $nota->status);
        $this->assertSame(98765, $nota->n_cod_os);
        $this->assertSame(600.0, (float) $nota->valor_total);
        $this->assertSame($prescricao->id.'/'.$nota->numero, $nota->codigo_integracao);

        Http::assertSent(function ($request) use ($prescricao, $nota) {
            $corpo = json_decode($request->body(), true);

            return ($corpo['call'] ?? null) === 'IncluirOS'
                && ($corpo['param'][0]['Cabecalho']['cCodIntOS'] ?? null) === $nota->codigo_integracao
                && ($corpo['param'][0]['Cabecalho']['nCodCli'] ?? null) === 123456
                && ($corpo['param'][0]['Cabecalho']['cEtapa'] ?? null) === '50'
                && ($corpo['param'][0]['ServicosPrestados'][0]['nCodServico'] ?? null) === 5311624010
                && (float) ($corpo['param'][0]['ServicosPrestados'][0]['nValUnit'] ?? 0) === 600.0;
        });

        Http::assertSent(function ($request) use ($nota) {
            $corpo = json_decode($request->body(), true);

            return ($corpo['call'] ?? null) === 'FaturarOS'
                && ($corpo['param'][0]['cCodIntOS'] ?? null) === $nota->codigo_integracao;
        });
    }

    public function test_nao_emite_sem_servico_selecionado(): void
    {
        $usuario = $this->usuario();
        $prescricao = Prescricao::query()->whereNotNull('paciente_id')->first();

        if (! $usuario || ! $prescricao) {
            $this->markTestSkipped('Sem usuário ou prescrição para o teste.');
        }

        $this->actingAs($usuario)
            ->postJson(route('prescricoes.fiscal.notas.store', $prescricao), [
                'quantidade' => '1',
                'valor_unitario' => '600,00',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('n_cod_servico');
    }
}
