<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integração dos pacientes com a Omie pela tela de Pacientes: botão do lote,
 * ação da linha e a coluna com o código do cliente na Omie.
 */
class PacientesOmieTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.feegow.base_url' => 'https://api.feegow.com/v1/api',
            'services.feegow.token' => 'token-de-teste',
            'services.omie.base_url' => 'https://app.omie.com.br/api/v1',
            'services.omie.app_key' => 'chave-de-teste',
            'services.omie.app_secret' => 'segredo-de-teste',
        ]);
    }

    private function usuario(): ?User
    {
        return User::orderBy('id')->first();
    }

    private function paciente(): ?Paciente
    {
        return Paciente::query()->whereNotNull('paciente_id')->orderBy('id')->first();
    }

    /**
     * Feegow com dados fiscais completos e Omie criando o cliente.
     */
    private function fakeIntegracoes(int $codigoOmie = 6060913944): void
    {
        Http::fake([
            'api.feegow.com/*' => Http::response([
                'content' => [
                    'nome' => 'Maria da Silva',
                    'email' => ['maria@exemplo.com', ''],
                    'documentos' => ['cpf' => '123.456.789-09'],
                    'endereco' => 'Rua das Flores',
                    'numero' => '10',
                    'bairro' => 'Centro',
                    'cidade' => 'São Paulo',
                    'estado' => 'SP',
                    'cep' => '01001-000',
                ],
            ]),
            'app.omie.com.br/*' => function ($request) use ($codigoOmie) {
                $corpo = json_decode($request->body(), true);

                if (($corpo['call'] ?? null) === 'ListarClientes') {
                    return Http::response([
                        'faultstring' => 'ERROR: Não existem registros para a página [1]!',
                        'faultcode' => 'SOAP-ENV:Client-5113',
                    ], 500);
                }

                return Http::response(['codigo_cliente_omie' => $codigoOmie]);
            },
        ]);
    }

    public function test_tela_traz_o_botao_e_o_codigo_da_integracao_na_tabela(): void
    {
        $usuario = $this->usuario();
        $paciente = $this->paciente();

        if (! $usuario || ! $paciente) {
            $this->markTestSkipped('Sem usuário ou paciente importado da Feegow.');
        }

        $paciente->forceFill(['codigo_cliente_omie' => 6060913944])->save();

        $this->actingAs($usuario)
            ->get(route('pacientes.index'))
            ->assertOk()
            ->assertSee('Integrar com a Omie')
            ->assertSee('Omie')
            ->assertSee('6060913944');
    }

    public function test_integra_um_paciente_pela_acao_da_linha(): void
    {
        $usuario = $this->usuario();
        $paciente = $this->paciente();

        if (! $usuario || ! $paciente) {
            $this->markTestSkipped('Sem usuário ou paciente importado da Feegow.');
        }

        $paciente->forceFill(['codigo_cliente_omie' => null, 'omie_integrado_em' => null])->save();
        $this->fakeIntegracoes(6065491819);

        $this->actingAs($usuario)
            ->post(route('pacientes.integrar-omie', $paciente))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(6065491819, (int) $paciente->fresh()->codigo_cliente_omie);
    }

    public function test_lote_integra_os_pendentes_e_informa_os_restantes(): void
    {
        $usuario = $this->usuario();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário cadastrado.');
        }

        // Estado previsível: todos pendentes (o rollback devolve a base).
        Paciente::query()->update(['codigo_cliente_omie' => null, 'omie_integrado_em' => null]);

        $total = Paciente::count();

        $this->fakeIntegracoes();

        $this->actingAs($usuario)
            ->postJson(route('pacientes.integrar-omie-lote'))
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('integrados', min($total, \App\Http\Controllers\PacienteController::LOTE_OMIE))
            ->assertJsonPath('restantes', max(0, $total - \App\Http\Controllers\PacienteController::LOTE_OMIE))
            ->assertJsonCount(0, 'falhas');

        $this->assertSame(
            min($total, \App\Http\Controllers\PacienteController::LOTE_OMIE),
            Paciente::whereNotNull('codigo_cliente_omie')->count()
        );
    }

    public function test_lote_devolve_os_pacientes_sem_dados_fiscais_e_nao_repete(): void
    {
        $usuario = $this->usuario();

        if (! $usuario) {
            $this->markTestSkipped('Sem usuário cadastrado.');
        }

        Paciente::query()->update(['codigo_cliente_omie' => null, 'omie_integrado_em' => null]);

        // A Feegow responde sem CPF: ninguém pode ser integrado.
        Http::fake([
            'api.feegow.com/*' => Http::response(['content' => ['nome' => 'Maria da Silva']]),
            'app.omie.com.br/*' => Http::response([]),
        ]);

        $resposta = $this->actingAs($usuario)
            ->postJson(route('pacientes.integrar-omie-lote'))
            ->assertOk()
            ->assertJsonPath('integrados', 0)
            ->assertJsonPath('ok', true);

        $falhas = $resposta->json('falhas');
        $this->assertNotEmpty($falhas);
        $this->assertArrayHasKey('id', $falhas[0]);
        $this->assertStringContainsString('CPF', $falhas[0]['motivo']);

        // Nenhum integrou: os mesmos pendentes continuam lá.
        $restantes = $resposta->json('restantes');
        $this->assertSame(Paciente::whereNull('codigo_cliente_omie')->count(), $restantes);

        // Enviando os que falharam como "ignorar", o lote avança para os próximos
        // em vez de insistir nos mesmos cadastros.
        $ignorar = array_column($falhas, 'id');

        $this->actingAs($usuario)
            ->postJson(route('pacientes.integrar-omie-lote'), ['ignorar' => $ignorar])
            ->assertOk()
            ->assertJsonPath('restantes', $restantes - count($ignorar));
    }
}
