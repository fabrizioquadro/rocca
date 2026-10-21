<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Integração com a API da Omie.
 *
 * Cada chamada é um POST no recurso com o envelope JSON-RPC da Omie:
 *
 *   {
 *     "call": "NomeDoMetodo",
 *     "app_key": "...",
 *     "app_secret": "...",
 *     "param": [ { ...campos do método... } ]
 *   }
 *
 * A Omie responde HTTP 200 mesmo quando recusa a chamada: nesse caso o corpo
 * traz "faultstring"/"faultcode" (por isso a checagem é feita no corpo).
 */
class OmieService
{
    private string $baseUrl;

    private ?string $appKey;

    private ?string $appSecret;

    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.omie.base_url'), '/');
        $this->appKey = config('services.omie.app_key');
        $this->appSecret = config('services.omie.app_secret');
        $this->timeout = (int) config('services.omie.timeout', 30);
    }

    /**
     * A integração está configurada?
     */
    public function configurado(): bool
    {
        return $this->baseUrl !== '' && ! empty($this->appKey) && ! empty($this->appSecret);
    }

    /**
     * Executa um método da API e devolve a resposta já decodificada.
     *
     * @throws RuntimeException
     */
    public function chamar(string $recurso, string $metodo, array $parametros = []): array
    {
        if (! $this->configurado()) {
            throw new RuntimeException('Integração com a Omie não está configurada (OMIE_APP_KEY / OMIE_APP_SECRET).');
        }

        $resposta = Http::timeout($this->timeout)
            ->connectTimeout(15)
            ->acceptJson()
            ->asJson()
            ->post($this->baseUrl.'/'.trim($recurso, '/').'/', [
                'call' => $metodo,
                'app_key' => $this->appKey,
                'app_secret' => $this->appSecret,
                'param' => [$parametros],
            ]);

        $corpo = $resposta->json();
        $erro = $this->mensagemDeErro($resposta, $corpo);

        if ($erro !== null) {
            Log::error('Omie: erro na chamada '.$metodo.' ('.$recurso.')', [
                'http' => $resposta->status(),
                'erro' => $erro,
            ]);

            throw new RuntimeException($metodo.': '.$erro);
        }

        return is_array($corpo) ? $corpo : [];
    }

    /**
     * Executa um método de listagem.
     *
     * A Omie recusa a chamada com "Não existem registros para a página" quando
     * a lista está vazia — para uma listagem isso é simplesmente "nenhum
     * registro", e não um erro.
     *
     * @throws RuntimeException
     */
    private function chamarLista(string $recurso, string $metodo, array $parametros = []): array
    {
        try {
            return $this->chamar($recurso, $metodo, $parametros);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Não existem registros')) {
                return [];
            }

            throw $e;
        }
    }

    /**
     * Lista os serviços cadastrados (cadastro fiscal usado na NFS-e).
     */
    public function listarServicos(int $pagina = 1, int $registrosPorPagina = 50): array
    {
        return $this->chamarLista('servicos/servico', 'ListarCadastroServico', [
            'nPagina' => $pagina,
            'nRegPorPagina' => $registrosPorPagina,
        ]);
    }

    /**
     * Formas/condições de pagamento aceitas na OS.
     */
    public function listarFormasPagamento(int $pagina = 1, int $registrosPorPagina = 50): array
    {
        return $this->chamarLista('produtos/formaspagvendas', 'ListarFormasPagVendas', [
            'pagina' => $pagina,
            'registros_por_pagina' => $registrosPorPagina,
        ]);
    }

    /**
     * Cidades (com o código IBGE) usadas no local de prestação do serviço.
     */
    public function pesquisarCidades(int $pagina = 1, int $registrosPorPagina = 50): array
    {
        return $this->chamarLista('geral/cidades', 'PesquisarCidades', [
            'pagina' => $pagina,
            'registros_por_pagina' => $registrosPorPagina,
        ]);
    }

    /**
     * Empresas (prestador) cadastradas na conta Omie.
     */
    public function listarEmpresas(int $pagina = 1, int $registrosPorPagina = 50): array
    {
        return $this->chamarLista('geral/empresas', 'ListarEmpresas', [
            'pagina' => $pagina,
            'registros_por_pagina' => $registrosPorPagina,
        ]);
    }

    /**
     * Traduz a resposta da Omie em uma mensagem de erro (null quando deu certo).
     */
    private function mensagemDeErro(Response $resposta, mixed $corpo): ?string
    {
        // A Omie devolve o motivo da recusa em "faultstring", inclusive com
        // HTTP 500 — por isso a checagem vem antes do status HTTP.
        if (is_array($corpo) && ! empty($corpo['faultstring'])) {
            return (string) $corpo['faultstring'];
        }

        if (! $resposta->successful()) {
            return 'HTTP '.$resposta->status().' — '.mb_substr(trim($resposta->body()), 0, 300);
        }

        if (! is_array($corpo)) {
            return 'resposta inválida (não é JSON)';
        }

        return null;
    }
}
