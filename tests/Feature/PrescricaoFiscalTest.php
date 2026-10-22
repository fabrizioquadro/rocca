<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\Prescricao;
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
}
