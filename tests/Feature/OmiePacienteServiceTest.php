<?php

namespace Tests\Feature;

use App\Models\Paciente;
use App\Services\OmiePacienteService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Integração do paciente (tomador da NFS-e) com a Omie: a Feegow fornece os
 * dados fiscais e a Omie recebe o cadastro do cliente pelo CPF.
 */
class OmiePacienteServiceTest extends TestCase
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

    private function paciente(): Paciente
    {
        $paciente = Paciente::query()
            ->whereNotNull('paciente_id')
            ->orderBy('id')
            ->first();

        if (! $paciente) {
            $this->markTestSkipped('Sem paciente importado da Feegow.');
        }

        return $paciente;
    }

    private function fakeFeegow(): void
    {
        Http::fake([
            'api.feegow.com/*' => Http::response([
                'content' => [
                    'nome' => 'Maria da Silva',
                    'email' => ['maria@exemplo.com', ''],
                    'celulares' => ['11999998888', ''],
                    'documentos' => ['cpf' => '123.456.789-09'],
                    'endereco' => 'Rua das Flores',
                    'numero' => '10',
                    'complemento' => 'Ap 1',
                    'bairro' => 'Centro',
                    'cidade' => 'São Paulo',
                    'estado' => 'sp',
                    'cep' => '01001-000',
                ],
            ]),
        ]);
    }

    public function test_monta_o_cadastro_do_cliente_com_os_dados_da_feegow(): void
    {
        $this->fakeFeegow();

        $dados = (new OmiePacienteService(
            app(\App\Services\FeegowService::class),
            app(\App\Services\OmieService::class),
        ))->dadosFiscais($this->paciente());

        $this->assertSame([], $dados['faltando']);

        $payload = $dados['payload'];

        $this->assertSame('Maria da Silva', $payload['razao_social']);
        $this->assertSame('12345678909', $payload['cnpj_cpf']);
        $this->assertSame('maria@exemplo.com', $payload['email']);
        $this->assertSame('Rua das Flores', $payload['endereco']);
        $this->assertSame('10', $payload['endereco_numero']);
        $this->assertSame('01001000', $payload['cep']);
        $this->assertSame('SP', $payload['estado']);
        $this->assertSame('11', $payload['telefone1_ddd']);
        $this->assertSame('999998888', $payload['telefone1_numero']);
        $this->assertArrayNotHasKey('codigo_cliente_integracao', $payload);
    }

    public function test_avisa_quais_dados_faltam_na_feegow(): void
    {
        Http::fake([
            'api.feegow.com/*' => Http::response([
                'content' => [
                    'nome' => 'Maria da Silva',
                    'documentos' => ['cpf' => ''],
                ],
            ]),
        ]);

        $dados = (new OmiePacienteService(
            app(\App\Services\FeegowService::class),
            app(\App\Services\OmieService::class),
        ))->dadosFiscais($this->paciente());

        $this->assertContains('CPF', $dados['faltando']);
        $this->assertContains('endereço', $dados['faltando']);
        $this->assertContains('CEP', $dados['faltando']);
    }

    public function test_cadastra_o_paciente_na_omie_e_grava_o_codigo(): void
    {
        $paciente = $this->paciente();
        $paciente->forceFill(['codigo_cliente_omie' => null])->save();

        Http::fake([
            'api.feegow.com/*' => Http::response([
                'content' => [
                    'nome' => 'Maria da Silva',
                    'email' => ['maria@exemplo.com', ''],
                    'documentos' => ['cpf' => '12345678909'],
                    'endereco' => 'Rua das Flores',
                    'numero' => '10',
                    'bairro' => 'Centro',
                    'cidade' => 'São Paulo',
                    'estado' => 'SP',
                    'cep' => '01001-000',
                ],
            ]),
            'app.omie.com.br/*' => function ($request) {
                $corpo = json_decode($request->body(), true);

                if (($corpo['call'] ?? null) === 'ListarClientes') {
                    return Http::response([
                        'faultstring' => 'ERROR: Não existem registros para a página [1]!',
                        'faultcode' => 'SOAP-ENV:Client-5113',
                    ], 500);
                }

                return Http::response(['codigo_cliente_omie' => 987654]);
            },
        ]);

        $resultado = (new OmiePacienteService(
            app(\App\Services\FeegowService::class),
            app(\App\Services\OmieService::class),
        ))->registrar($paciente);

        $this->assertSame(['codigo' => 987654, 'criado' => true], $resultado);
        $this->assertSame(987654, $paciente->fresh()->codigo_cliente_omie);

        Http::assertSent(function ($request) {
            $corpo = json_decode($request->body(), true);

            return ($corpo['call'] ?? null) === 'UpsertClienteCpfCnpj'
                && ($corpo['param'][0]['endereco'] ?? null) === 'Rua das Flores'
                && ! isset($corpo['param'][0]['codigo_cliente_integracao']);
        });
    }
}
