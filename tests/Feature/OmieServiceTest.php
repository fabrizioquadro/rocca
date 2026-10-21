<?php

namespace Tests\Feature;

use App\Services\OmieService;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/**
 * Envelope e tratamento de erro da integração com a API da Omie.
 */
class OmieServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.omie.base_url' => 'https://app.omie.com.br/api/v1',
            'services.omie.app_key' => 'chave-de-teste',
            'services.omie.app_secret' => 'segredo-de-teste',
            'services.omie.timeout' => 30,
        ]);
    }

    public function test_envia_o_envelope_exigido_pela_omie(): void
    {
        Http::fake([
            'app.omie.com.br/*' => Http::response(['empresas' => [], 'total_de_registros' => 0]),
        ]);

        $retorno = (new OmieService)->listarEmpresas();

        $this->assertSame(0, $retorno['total_de_registros']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://app.omie.com.br/api/v1/geral/empresas/'
                && $request['call'] === 'ListarEmpresas'
                && $request['app_key'] === 'chave-de-teste'
                && $request['app_secret'] === 'segredo-de-teste'
                && $request['param'] === [['pagina' => 1, 'registros_por_pagina' => 50]];
        });
    }

    public function test_usa_os_nomes_de_paginacao_do_cadastro_de_servicos(): void
    {
        Http::fake([
            'app.omie.com.br/*' => Http::response(['cadastros' => []]),
        ]);

        (new OmieService)->listarServicos();

        Http::assertSent(fn ($request) => $request['call'] === 'ListarCadastroServico'
            && $request['param'] === [['nPagina' => 1, 'nRegPorPagina' => 50]]);
    }

    public function test_lanca_excecao_quando_a_omie_recusa_a_chamada(): void
    {
        Http::fake([
            'app.omie.com.br/*' => Http::response([
                'faultstring' => 'ERROR: App Key inválida',
                'faultcode' => 'soap:Client',
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ListarEmpresas: ERROR: App Key inválida');

        (new OmieService)->listarEmpresas();
    }

    public function test_lanca_excecao_em_erro_de_http(): void
    {
        Http::fake([
            'app.omie.com.br/*' => Http::response('erro interno', 500),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('HTTP 500');

        (new OmieService)->listarEmpresas();
    }

    public function test_nao_chama_a_api_quando_nao_esta_configurado(): void
    {
        config([
            'services.omie.app_key' => null,
            'services.omie.app_secret' => null,
        ]);

        Http::fake();

        try {
            (new OmieService)->listarEmpresas();
            $this->fail('Era esperada uma exceção de configuração.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('não está configurada', $e->getMessage());
        }

        Http::assertNothingSent();
    }
}
